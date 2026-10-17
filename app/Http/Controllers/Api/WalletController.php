<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index(): JsonResponse
    {
        $wallets = Auth::user()->wallets;

        return response()->json([
            'data' => $wallets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'balance' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'type' => 'required|string|in:general,savings,credit',
        ]);

        $wallet = Auth::user()->wallets()->create($validated);

        return response()->json([
            'data' => $wallet,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $wallet = Auth::user()->wallets()->findOrFail($id);

        return response()->json([
            'data' => $wallet,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wallet = Auth::user()->wallets()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'balance' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'type' => 'sometimes|string|in:general,savings,credit',
            'is_active' => 'sometimes|boolean',
        ]);

        $wallet->update($validated);

        return response()->json([
            'data' => $wallet->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $wallet = Auth::user()->wallets()->findOrFail($id);
        $wallet->delete();

        return response()->json([
            'message' => 'Wallet deleted successfully',
        ]);
    }
}
