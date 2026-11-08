@include('admin.layout.head')
<style>
    .dashboard {
        padding: 20px;
    }

    .stat-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        padding: 24px 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        border: 1px solid #f0f0f0;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #667eea, #764ba2);
    }

    .stat-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .stat-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #fff;
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-today {
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 12px;
        display: inline-block;
    }

    .trend-up {
        background: #f0f9ff;
        color: #1890ff;
    }

    .trend-zero {
        background: #f5f5f5;
        color: #999;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #333;
        line-height: 1.2;
        margin-bottom: 6px;
    }

    .stat-label {
        font-size: 14px;
        color: #999;
    }
</style>

<div class="dashboard">
    <div class="layui-row layui-col-space15">
        <div class="layui-col-md6">
            <div class="stat-card" onclick="window.location.href='/admin/mall/customer'">
                <div class="stat-card-top">
                    <div class="stat-icon-box"><i class="fa fa-users"></i></div>
                    @if(($statistics['todayCustomer'] ?? 0) > 0)
                    <span class="stat-today trend-up">+{{$statistics['todayCustomer'] ?? 0}} 今日</span>
                    @else
                    <span class="stat-today trend-zero">今日无新增</span>
                    @endif
                </div>
                <div class="stat-value">{{$statistics['customerTotal'] ?? 0}}</div>
                <div class="stat-label">客户总数</div>
            </div>
        </div>
        <div class="layui-col-md6">
            <div class="stat-card" onclick="window.location.href='/admin/health/profile'">
                <div class="stat-card-top">
                    <div class="stat-icon-box" style="background: linear-gradient(135deg, #a18cd1, #fbc2eb);"><i class="fa fa-id-card-o"></i></div>
                </div>
                <div class="stat-value">{{$statistics['profileTotal'] ?? 0}}</div>
                <div class="stat-label">健康档案总数</div>
            </div>
        </div>
    </div>
</div>

@include('admin.layout.foot')
