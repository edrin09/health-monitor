define(["jquery", "easy-admin"], function ($, ea) {

    var init = {
        table_elem: '#currentTable',
        table_render_id: 'currentTableRenderId',
        index_url: 'system/config/index',
    };

    var Controller = {

        save: function () {
            ea.listen();
        },
    };
    return Controller;
});
