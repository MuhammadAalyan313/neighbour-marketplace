<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Models\Order;

class OrderNotificationService
{
    public function __construct(
        protected NotificationOutboxService $outbox,
        protected OrderReceiptService $receipt,
        protected NotificationInboxService $inbox
    ) {}

    public function created(Order $order): void
    {
        $order->loadMissing([
            'store.owner',
            'buyer',
            'items',
        ]);

        $receipt = $this->receipt->data($order);
        $seller = $order->store?->owner;
        $buyer = $order->buyer;

        /*
        |--------------------------------------------------------------------------
        | Seller - Push + In-App Notification
        |--------------------------------------------------------------------------
        */

        if ($seller) {
            $this->outbox->create(
                type: 'order_created',
                channel: NotificationChannel::PUSH,
                notifiable: $seller,
                related: $order,
                payload: [
                    'title' => 'New Order #'.$order->order_number,
                    'body' => 'A new order has been placed.',
                    'url' => '/seller/orders/'.$order->order_number,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:seller:push:created"
            );

            $this->inbox->create(
                user: $seller,
                type: 'order_created',
                title: 'New Order #'.$order->order_number,
                body: 'New order received. Total: '
                    .$receipt['total_amount'],
                url: '/seller/orders/'.$order->order_number,
                data: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:seller:created"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Buyer - Push + In-App Receipt
        |--------------------------------------------------------------------------
        */

        if ($buyer) {
            $this->outbox->create(
                type: 'order_created',
                channel: NotificationChannel::PUSH,
                notifiable: $buyer,
                related: $order,
                payload: [
                    'title' => 'Order Confirmed',
                    'body' => 'Order #'.$order->order_number
                        .' has been placed successfully.',
                    'url' => '/orders/'.$order->order_number,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:buyer:push:created"
            );

            $this->inbox->create(
                user: $buyer,
                type: 'order_created',
                title: 'Order Confirmed #'.$order->order_number,
                body: 'Your order was confirmed. Total: '
                    .$receipt['total_amount'],
                url: '/orders/'.$order->order_number,
                data: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:buyer:created"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Email Receipt - Registered + Guest Buyers
        |--------------------------------------------------------------------------
        */

        $this->queueReceiptEmail($order, false);
    }

    public function statusUpdated(Order $order): void
    {
        $order->loadMissing([
            'store.owner',
            'buyer',
            'items',
        ]);

        $receipt = $this->receipt->data($order);
        $status = $order->status->value;

        $seller = $order->store?->owner;
        $buyer = $order->buyer;

        $statusLabel = str_replace('_', ' ', $status);

        /*
        |--------------------------------------------------------------------------
        | Seller - Push + In-App Status Update
        |--------------------------------------------------------------------------
        */

        if ($seller) {
            $this->outbox->create(
                type: 'order_status_updated',
                channel: NotificationChannel::PUSH,
                notifiable: $seller,
                related: $order,
                payload: [
                    'title' => 'Order Status Updated',
                    'body' => 'Order #'.$order->order_number
                        .' is now '.$statusLabel.'.',
                    'url' => '/seller/orders/'.$order->order_number,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:seller:push:status:{$status}"
            );

            $this->inbox->create(
                user: $seller,
                type: 'order_status_updated',
                title: 'Order #'.$order->order_number.' Updated',
                body: 'Order status: '.$statusLabel,
                url: '/seller/orders/'.$order->order_number,
                data: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:seller:status:{$status}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Buyer - Push + In-App Status Update
        |--------------------------------------------------------------------------
        */

        if ($buyer) {
            $this->outbox->create(
                type: 'order_status_updated',
                channel: NotificationChannel::PUSH,
                notifiable: $buyer,
                related: $order,
                payload: [
                    'title' => 'Order Update',
                    'body' => 'Your order #'.$order->order_number
                        .' is now '.$statusLabel.'.',
                    'url' => '/orders/'.$order->order_number,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:buyer:push:status:{$status}"
            );

            $this->inbox->create(
                user: $buyer,
                type: 'order_status_updated',
                title: 'Your Order #'.$order->order_number,
                body: 'Order status: '.$statusLabel,
                url: '/orders/'.$order->order_number,
                data: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $status,
                    'receipt' => $receipt,
                ],
                deduplicationKey:
                    "order:{$order->id}:buyer:status:{$status}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Email Order Status + Updated Receipt
        |--------------------------------------------------------------------------
        */

        $this->queueReceiptEmail($order, true);
    }

    private function queueReceiptEmail(
        Order $order,
        bool $statusUpdate
    ): void {
        $email = $order->buyer_email
            ?: $order->buyer?->email;

        if (
            ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        $status = $order->status?->value ?? 'pending';

        $subject = $statusUpdate
            ? 'Order Update #'.$order->order_number
            : $this->receipt->subject($order);

        $body = $this->receipt->message($order);

        // Guest checkout mein Order model notifiable hoga.
        $notifiable = $order->buyer ?? $order;

        $this->outbox->create(
            type: $statusUpdate
                ? 'order_status_updated'
                : 'order_created',
            channel: NotificationChannel::EMAIL,
            notifiable: $notifiable,
            related: $order,
            payload: [
                'email' => $email,
                'subject' => $subject,
                'body' => $body,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'receipt' => $this->receipt->data($order),
            ],
            deduplicationKey: $statusUpdate
                ? "order:{$order->id}:buyer:email:status:{$status}"
                : "order:{$order->id}:buyer:email:created"
        );
    }

    
public function paymentConfirmed(Order $order): void
{
    $order->loadMissing([
        'store.owner',
        'buyer',
        'items',
    ]);

    $buyer = $order->buyer;

    $receipt = $this->receipt->data($order);

    $title = 'Payment Confirmed';

    $message = 'Payment received for order #'
        . $order->order_number
        . '. Thank you!';

    /*
    |--------------------------------------------------------------------------
    | Registered Buyer - Push + In-App
    |--------------------------------------------------------------------------
    */

    if ($buyer) {
        $this->outbox->create(
            type: 'order_payment_confirmed',
            channel: NotificationChannel::PUSH,
            notifiable: $buyer,
            related: $order,
            payload: [
                'title' => $title,
                'body' => $message,
                'url' => '/orders/' . $order->order_number,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'paid',
            ],
            deduplicationKey:
                "order:{$order->id}:buyer:push:payment_confirmed"
        );

        $this->inbox->create(
            user: $buyer,
            type: 'order_payment_confirmed',
            title: $title,
            body: $message,
            url: '/orders/' . $order->order_number,
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'paid',
            ],
            deduplicationKey:
                "order:{$order->id}:buyer:payment_confirmed"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buyer Email - Registered + Guest
    |--------------------------------------------------------------------------
    */

    $email = $order->buyer_email
        ?: $buyer?->email;

    if (
        ! is_string($email) ||
        ! filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        return;
    }

    $notifiable = $buyer ?? $order;

    $this->outbox->create(
        type: 'order_payment_confirmed',
        channel: NotificationChannel::EMAIL,
        notifiable: $notifiable,
        related: $order,
        payload: [
            'email' => $email,
            'subject' =>
                'Payment Confirmed - Order #' . $order->order_number,
            'body' => implode("\n", [
                'Hello ' . $order->buyer_name . ',',
                '',
                'Your payment has been confirmed by the seller.',
                'Order: ' . $order->order_number,
                'Amount: ' . $order->total_amount,
                'Payment method: ' . $order->payment_method,
                'Payment status: Paid',
                '',
                'Thank you for your order.',
            ]),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'receipt' => $receipt,
        ],
        deduplicationKey:
            "order:{$order->id}:buyer:email:payment_confirmed"
    );
}

}
