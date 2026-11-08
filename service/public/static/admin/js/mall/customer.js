define(["jquery", "easy-admin"], function ($, ea) {

    var init = {
        pk: 'customer_id',
        table_elem: '#currentTable',
        table_render_id: 'currentTableRenderId',
        index_url: auth_header + '/index',
        modify_url: auth_header + '/modify',
    };

    var Controller = {

        index: function () {
            ea.table.render({
                init: init,
                where: ea.getUrlParams(['customer_id']),
                cols: [[
                    {
                        field: 'customer_id',
                        minWidth: 80,
                        title: 'ID',
                        search: false,
                    },
                    {
                        field: 'nickname',
                        minWidth: 120,
                        title: '客户昵称'
                    },
                    {
                        field: 'phone_number',
                        minWidth: 120,
                        title: '客户手机号',
                        templet: function (d) {
                            if (d.phone_number == 0) {
                                return '<span style="color:#999;">-</span>';
                            }
                            return '<div>' + d.phone_number + '</div>';
                        },
                    },
                    {
                        field: 'expansion.is_freeze',
                        title: '账户状态',
                        width: 100,
                        search: 'select',
                        selectList: {0: '正常', 1: '冻结'},
                        templet: function (d) {
                            if (d.expansion && d.expansion.is_freeze === 1) {
                                return '<span class="layui-badge-rim layui-bg-red">冻结</span>';
                            } else {
                                return '<span class="layui-badge layui-bg-blue">正常</span>';
                            }
                        }
                    },
                    {
                        field: 'create_time',
                        minWidth: 200,
                        title: '创建时间',
                        search: 'range',
                        sort: true
                    },
                    {
                        width: 200,
                        title: '操作',
                        templet: ea.table.tool,
                        fixed: 'right',
                        operat: [
                            [
                                {
                                    text: '详情',
                                    url: '/mall/customer/info',
                                    method: 'open',
                                    auth: 'info',
                                    class: 'layui-btn layui-btn-xs layui-btn-info',
                                    field: 'customer_id'
                                }
                            ],
                        ]
                    }
                ]],
            });

            ea.listen();
        },
        add: function () {
            ea.listen();
        },
        edit: function () {
            ea.listen();
        },
        info: function () {
            ea.listen();
        },
    };

    return Controller;
});