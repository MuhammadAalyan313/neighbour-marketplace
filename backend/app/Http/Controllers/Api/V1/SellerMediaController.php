<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class SellerMediaController extends Controller
{
    /**
     * Upload product photos or store logos to the existing public disk.
     * Never trust the client-provided file name as a storage path.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['product', 'store'])],
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $path = Storage::disk('public')->putFile(
            'marketplace/'.$validated['type'],
            $validated['image']
        );

        if ($path === false) {
            throw new RuntimeException('Unable to save the uploaded image.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'data' => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }
}
