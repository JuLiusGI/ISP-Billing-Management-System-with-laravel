<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesSubscriptionInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    use HandlesSubscriptionInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('subscription')) ?? false;
    }
}
