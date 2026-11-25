<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Realtime Test</title>

    @vite(['resources/js/app.js'])
</head>

<body>

    <h1>Seller Realtime Test</h1>

    <div id="status">
        Connecting...
    </div>

    <div id="orders"></div>

<script>
    window.addEventListener('load', function () {

        const sellerId = {{ auth()->id() }};

        console.log('Seller ID:', sellerId);
        console.log('Echo:', window.Echo);

        if (!window.Echo) {
            console.error('Laravel Echo is not initialized.');
            document.getElementById('status').innerText =
                'Echo failed to initialize.';
            return;
        }

        window.Echo
            .private(`seller.${sellerId}`)
            .listen('.order.created', (event) => {

                console.log('ORDER CREATED EVENT:', event);

                const orders =
                    document.getElementById('orders');

                orders.insertAdjacentHTML(
                    'afterbegin',
                    `
                    <div style="
                        padding:20px;
                        border:1px solid #ccc;
                        margin:10px 0;
                    ">
                        <strong>New Order</strong>

                        <p>
                            Order:
                            ${event.order.order_number}
                        </p>

                        <p>
                            Buyer:
                            ${event.order.buyer_name}
                        </p>

                        <p>
                            House:
                            ${event.order.buyer_house_number}
                        </p>

                        <p>
                            Total:
                            ${event.order.total_amount}
                        </p>

                        <p>
                            Status:
                            ${event.order.status}
                        </p>
                    </div>
                    `
                );
            });

        document.getElementById('status').innerText =
            'Listening for seller orders...';
    });
</script>
</body>
</html>