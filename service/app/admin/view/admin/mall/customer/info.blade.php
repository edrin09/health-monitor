@include('admin.layout.head')
<div class="layuimini-container">
    <div class="layuimini-main">
        <div class="layui-card">
            <div class="layui-card-body">
                <table class="layui-table">
                    <tr>
                        <th style="width:150px;">客户昵称</th>
                        <td>{{$customer['nickname']}}</td>
                        <th>客户手机号</th>
                        <td>{{$customer['phone_number'] ?: '-'}}</td>
                    </tr>
                    <tr>
                        <th>创建时间</th>
                        <td>{{$customer['create_time']}}</td>
                        <th>账户状态</th>
                        <td>
                            @if(($customer['expansion']['is_freeze'] ?? 0) != 1)
                                <span style="color:#009688;font-weight:bold;">正常</span>
                            @else
                                <span style="color:#FF5722;font-weight:bold;">已冻结</span>
                            @endif
                        </td>
                    </tr>
                </table>

                @if($profile)
                <fieldset class="layui-elem-field layui-field-title" style="margin-top: 20px;">
                    <legend>健康档案</legend>
                </fieldset>
                <table class="layui-table">
                    <tr>
                        <th style="width:150px;">身高</th>
                        <td>{{$profile['height']}} cm</td>
                        <th style="width:150px;">性别</th>
                        <td>@if($profile['gender'] == 1) 男 @elseif($profile['gender'] == 2) 女 @else 未知 @endif</td>
                    </tr>
                    <tr>
                        <th>出生日期</th>
                        <td>{{$profile['birth_date'] ?: '-'}}</td>
                    </tr>
                </table>
                @endif
            </div>
        </div>

        <!-- 操作按钮 -->
        <div style="padding: 15px 20px; border-top: 1px solid #e6e6e6;">
            <button class="layui-btn @if(($customer['expansion']['is_freeze'] ?? 0) != 1) layui-btn-danger @else layui-btn-normal @endif" id="btnFreeze">
                @if(($customer['expansion']['is_freeze'] ?? 0) != 1)
                    冻结账户
                @else
                    解冻账户
                @endif
            </button>
        </div>
    </div>
</div>

<script>
    let pk = "{{$data['pk']}}";
    let cid = "{{$data['cid']}}";
    let auth_header = "{{$data['auth_header']}}";
    let customerId = {{$customer['customer_id']}};
</script>

<script src="/static/plugs/layui-v2.x/layui.js"></script>
<script>
layui.use(['layer', 'form'], function(){
    var layer = layui.layer;
    var form = layui.form;
    var $ = layui.jquery;

    // 冻结账户
    $('#btnFreeze').on('click', function(){
        var currentStatus = {{$customer['expansion']['is_freeze'] ?? 0}};
        var isFrozen = currentStatus == 1;
        var actionText = isFrozen ? '解冻' : '冻结';
        var confirmText = isFrozen ? '确定要解冻该账户吗？' : '确定要冻结该账户吗？';

        layer.confirm(confirmText, {
            title: actionText + '账户确认',
            btn: ['确认' + actionText, '取消'],
        }, function(index){
            var newStatus = isFrozen ? 0 : 1;
            $.post('/' + CONFIG.ADMIN + '/' + auth_header + '/freezeAccount', { customer_id: customerId, freeze_status: newStatus }, function(res){
                if (res.code == 1) {
                    layer.msg(res.msg, {icon: 1}, function(){
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.close(index);
                        parent.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 2});
                }
            });
            layer.close(index);
        });
    });

    form.render();
});
</script>

@include('admin.layout.foot')
