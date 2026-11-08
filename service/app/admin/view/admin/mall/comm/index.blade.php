@include('admin.layout.head')
<div class="layuimini-container">
    <div class="layuimini-main">
        <table id="currentTable" class="layui-table layui-hide"
               data-auth-add="{{$auth['auth_add']}}"
               data-auth-edit="{{$auth['auth_edit']}}"
               data-auth-delete="{{$auth['auth_del']}}"
               data-auth-export="{{$auth['auth_export']}}"
               data-auth-import="{{$auth['auth_import']}}"
               data-auth-item="{{$auth['auth_item']}}"
               data-auth-info="{{$auth['auth_info']}}"
               data-auth-review="{{$auth['auth_review']}}"

               data-auth-agent="{{$auth['auth_agent']}}"
               data-auth-adjust="{{$auth['auth_adjust']}}"
               data-auth-subordinate="{{$auth['auth_subordinate']}}"
               data-auth-commission="{{$auth['auth_commission']}}"

               lay-filter="currentTable">
        </table>
    </div>
</div>
<script>
    let pk = "{{$data['pk']}}";
    let cid = "{{$data['cid']}}";
    let auth_header = "{{$data['auth_header']}}";
</script>
@include('admin.layout.foot')