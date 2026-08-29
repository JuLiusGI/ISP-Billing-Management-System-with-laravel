<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesSubscriptionInput;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    use HandlesSubscriptionInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Subscription::class) ?? false;
    }
}
