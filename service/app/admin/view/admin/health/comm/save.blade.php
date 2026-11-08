@include('admin.layout.head')
<div class="layuimini-container">
    <div class="layuimini-main">
        <form class="layui-form layuimini-form" action="">
            @foreach($formFields as $field)
            <div class="layui-form-item">
                <label class="layui-form-label">{{$field['label']}}</label>
                <div class="layui-input-block">
                    @if($field['type'] == 'text')
                    <input type="{{$field['input_type'] ?? 'text'}}" name="{{$field['name']}}" lay-verify="required" class="layui-input" value="{{$row[$field['name']] ?? ''}}">
                    @elseif($field['type'] == 'select')
                    <select name="{{$field['name']}}" lay-verify="required">
                        @foreach($field['options'] as $optVal => $optLabel)
                        <option value="{{$optVal}}" {{isset($row[$field['name']]) && $row[$field['name']] == $optVal ? 'selected' : ''}}>{{$optLabel}}</option>
                        @endforeach
                    </select>
                    @elseif($field['type'] == 'textarea')
                    <textarea name="{{$field['name']}}" class="layui-textarea">{{$row[$field['name']] ?? ''}}</textarea>
                    @endif
                </div>
            </div>
            @endforeach
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="saveBtn">保存</button>
                </div>
            </div>
        </form>
    </div>
</div>
@include('admin.layout.foot')
