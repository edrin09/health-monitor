@include('admin.layout.head')
<div class="layuimini-container">
    <div class="layuimini-main">
        <form class="layui-form layuimini-form" action="">
            <div class="layui-tab layui-tab-brief" lay-filter="agreementTab">
                <ul class="layui-tab-title">
                    <li class="layui-this">用户服务协议</li>
                    <li>隐私政策</li>
                </ul>
                <div class="layui-tab-content">
                    <div class="layui-tab-item layui-show">
                        <div class="layui-form-item">
                            <label class="layui-form-label">协议内容</label>
                            <div class="layui-input-block">
                                {!!editor_textarea(sysconfig('site', 'user_agreement'),'user_agreement','请输入用户服务协议内容') !!}
                            </div>
                        </div>
                    </div>
                    <div class="layui-tab-item">
                        <div class="layui-form-item">
                            <label class="layui-form-label">隐私内容</label>
                            <div class="layui-input-block">
                                {!!editor_textarea(sysconfig('site', 'privacy_policy'),'privacy_policy','请输入隐私政策内容') !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="saveBtn">保存</button>
                </div>
            </div>
        </form>
    </div>
</div>
@include('admin.layout.foot')