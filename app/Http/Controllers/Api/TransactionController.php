<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(): JsonResponse
    {
        $transactions = Auth::user()->wallets()
            ->with('transactions.category')
            ->get()
            ->pluck('transactions')
            ->flatten();

        return response()->json([
            'data' => $transactions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', Auth::id())],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense,transfer',
            'transaction_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $wallet = Auth::user()->wallets()->findOrFail($validated['wallet_id']);
        $transaction = $wallet->transactions()->create($validated);

        return response()->json([
            'data' => $transaction,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $transaction = Auth::user()->wallets()
            ->with('transactions.category')
            ->get()
            ->pluck('transactions')
            ->flatten()
            ->firstWhere('id', $id);

        if (! $transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'data' => $transaction,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $transaction = Auth::user()->wallets()
            ->with('transactions')
            ->get()
            ->pluck('transactions')
            ->flatten()
            ->firstWhere('id', $id);

        if (! $transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', Auth::id())],
            'description' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric',
            'type' => 'sometimes|in:income,expense,transfer',
            'transaction_date' => 'sometimes|date',
            'reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $transaction->update($validated);

        return response()->json([
            'data' => $transaction->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $transaction = Auth::user()->wallets()
            ->with('transactions')
            ->get()
            ->pluck('transactions')
            ->flatten()
            ->firstWhere('id', $id);

        if (! $transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }
}
