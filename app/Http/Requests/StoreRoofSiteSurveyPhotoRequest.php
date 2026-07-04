<?php

namespace App\Http\Requests;

use App\Models\RoofSiteSurveyPhoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoofSiteSurveyPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'category' => ['required', Rule::in(RoofSiteSurveyPhoto::CATEGORIES)],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
