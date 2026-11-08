define(["jquery", "easy-admin"], function ($, ea) {

    var init = {
        pk: 'id',
        table_elem: '#currentTable',
        table_render_id: 'currentTableRenderId',
        index_url: auth_header + '/index',
        modify_url: auth_header + '/modify',
        delete_url: auth_header + '/delete',
    };

    var Controller = {

        index: function () {
            ea.table.render({
                init: init,
                cols: [[
                    {field: 'id', minWidth: 80, title: 'ID', search: false},
                    {field: 'customer_id', minWidth: 100, title: '客户ID'},
                    {
                        field: 'customer_nickname',
                        minWidth: 120,
                        title: '客户昵称',
                        search: false
                    },
                    {
                        field: 'customer_phone',
                        minWidth: 120,
                        title: '手机号',
                        search: false
                    },
                    {field: 'height', minWidth: 100, title: '身高(cm)', search: false},
                    {
                        field: 'gender',
                        minWidth: 80,
                        title: '性别',
                        search: 'select',
                        selectList: {0: '未知', 1: '男', 2: '女'},
                        templet: function (d) {
                            var map = {0: '未知', 1: '男', 2: '女'};
                            return map[d.gender] || '未知';
                        }
                    },
                    {field: 'birth_date', minWidth: 120, title: '出生日期', search: false},
                    {
                        field: 'create_time',
                        minWidth: 180,
                        title: '创建时间',
                        search: 'range',
                        sort: true
                    },
                    {
                        width: 120,
                        title: '操作',
                        templet: ea.table.tool,
                        fixed: 'right',
                        operat: [
                            [{
                                text: '编辑',
                                url: auth_header + '/edit',
                                method: 'open',
                                auth: 'edit',
                                class: 'layui-btn layui-btn-xs layui-btn-normal',
                            }, {
                                text: '删除',
                                url: auth_header + '/delete',
                                method: 'post',
                                auth: 'delete',
                                class: 'layui-btn layui-btn-xs layui-btn-danger',
                            }]
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
    };

    return Controller;
});