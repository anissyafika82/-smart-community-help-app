<?php

namespace App\Http\Requests\Chat;

use App\Models\ItemClaim;
use App\Models\ItemReport;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Only the item report's owner and claimants who have an active claim
     * against it may message each other about it — and only as an actual
     * pair.
     */
    public function authorize(): bool
    {
        /** @var ItemReport $itemReport */
        $itemReport = $this->route('itemReport');
        /** @var User $otherUser */
        $otherUser = $this->route('user');
        $me = $this->user();

        if (! $itemReport || ! $otherUser || ! $me || $me->id === $otherUser->id) {
            return false;
        }

        $isOwnerToClaimant = $itemReport->user_id === $me->id
            && ItemClaim::where('item_report_id', $itemReport->id)->where('claimant_id', $otherUser->id)->exists();

        $isClaimantToOwner = $itemReport->user_id === $otherUser->id
            && ItemClaim::where('item_report_id', $itemReport->id)->where('claimant_id', $me->id)->exists();

        return $isOwnerToClaimant || $isClaimantToOwner;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
