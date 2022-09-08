<?php

namespace App\Http\Requests;

use App\Rules\CheckPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class UserRegistration extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|email|indisposable|unique:users',
            'username' => 'required|unique:users',
            'first_name' => 'required|string|max:50',
            'middle_name' => 'sometimes|max:50',
            'last_name' => 'sometimes|string|max:50',
            'confirm_password' => 'required|min:8|max:16|same:password',
            'password' => ['required', new CheckPassword()],
            'department_id' => 'sometimes|numeric|exists:departments,id',
            'designation_id' => 'sometimes|numeric|exists:designations,id',
            'mobile_no' => 'required|min:10|max:10',
            'address' => 'sometimes|string'
        ];
    }

    public function messages()
    {
        return [
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => trans('messages.error_data'),
            'data' => $validator->errors()
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
