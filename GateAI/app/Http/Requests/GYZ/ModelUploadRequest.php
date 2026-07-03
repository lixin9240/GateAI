<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ModelUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file'         => 'required|file|mimes:onnx,pt,pth,h5,pb,zip|max:524288',
            'name'         => 'required|string|max:100',
            'version'      => 'required|string|max:30',
            'type'         => 'required|string|in:lstm_prediction,dqn_decision,fault_detection,general',
            'framework'    => 'nullable|string|in:tensorflow,pytorch,onnx,custom',
            'description'  => 'nullable|string|max:500',
            'accuracy'     => 'nullable|numeric|min:0|max:100',
            'training_dataset' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'     => '模型文件不能为空',
            'file.mimes'        => '模型文件格式不支持，支持：onnx, pt, pth, h5, pb, zip',
            'file.max'          => '模型文件大小不能超过512MB',
            'name.required'     => '模型名称不能为空',
            'version.required'  => '版本号不能为空',
            'type.required'     => '模型类型不能为空',
            'type.in'           => '模型类型不合法',
        ];
    }
}
