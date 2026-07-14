<?php

namespace App\Http\Requests\Chat;

use App\Models\AssistanceRequest;
use App\Models\HelpOffer;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Only the help offer's helper and requesters who have requested a
     * portion of it may message each other about it — and only as an
     * actual pair.
     */
    public function authorize(): bool
    {
        /** @var HelpOffer $helpOffer */
        $helpOffer = $this->route('helpOffer');
        /** @var User $otherUser */
        $otherUser = $this->route('user');
        $me = $this->user();

        if (! $helpOffer || ! $otherUser || ! $me || $me->id === $otherUser->id) {
            return false;
        }

        $isHelperToRequester = $helpOffer->helper_id === $me->id
            && AssistanceRequest::where('help_offer_id', $helpOffer->id)->where('requester_id', $otherUser->id)->exists();

        $isRequesterToHelper = $helpOffer->helper_id === $otherUser->id
            && AssistanceRequest::where('help_offer_id', $helpOffer->id)->where('requester_id', $me->id)->exists();

        return $isHelperToRequester || $isRequesterToHelper;
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
