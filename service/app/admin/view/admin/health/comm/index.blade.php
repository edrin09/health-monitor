@include('admin.layout.head')
<div class="layuimini-container">
    <div class="layuimini-main">
        <table id="currentTable" class="layui-table layui-hide"
               data-auth-add="{{$auth['auth_add']}}"
               data-auth-edit="{{$auth['auth_edit']}}"
               data-auth-delete="{{$auth['auth_del']}}"
               data-auth-export="{{$auth['auth_export']}}"
               data-auth-item="{{$auth['auth_item']}}"
               lay-filter="currentTable">
        </table>
    </div>
</div>
<script>
    let pk = "{{$data['pk']}}";
    let auth_header = "{{$data['auth_header']}}";
</script>
@include('admin.layout.foot')
