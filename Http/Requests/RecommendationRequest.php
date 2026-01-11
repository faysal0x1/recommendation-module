<?php
namespace App\Modules\Recommendation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => 'nullable|integer',
            'product_id'    => 'nullable|integer',
            'product_ids'   => 'nullable|array',
            'product_ids.*' => 'integer',
            'category_id'   => 'nullable|integer',
            'session_id'    => 'nullable|string',
            'context'       => 'required|string|in:home,product_page,cart,email,checkout',
            'algorithm'     => 'nullable|string',
            'limit'         => 'nullable|integer|min:1|max:50',
            'variant'       => 'nullable|string',
        ];
    }
}
