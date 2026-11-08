<!--
页面路径: /pages/stats/index
页面功能: 健康统计 - 按类型(血糖/血压/体重/腰围)和周期(7/30/90天)查看趋势图、统计数据、详细记录
-->
<template>
  <view class="container">
    <view class="custom-nav">
      <view class="nav-content">
        <text class="nav-title">健康统计</text>
      </view>
    </view>

    <view class="tab-bar">
      <view class="tab-item" :class="{ active: activeTab === 'glucose' }" @click="switchTab('glucose')">
        <text>🩸 血糖</text>
      </view>
      <view class="tab-item" :class="{ active: activeTab === 'blood_pressure' }" @click="switchTab('blood_pressure')">
        <text>💗 血压</text>
      </view>
      <view class="tab-item" :class="{ active: activeTab === 'weight' }" @click="switchTab('weight')">
        <text>⚖️ 体重</text>
      </view>
      <view class="tab-item" :class="{ active: activeTab === 'waist' }" @click="switchTab('waist')">
        <text>📏 腰围</text>
      </view>
    </view>

    <view class="period-bar">
      <view class="period-item" :class="{ active: period === 7 }" @click="switchPeriod(7)">近7天</view>
      <view class="period-item" :class="{ active: period === 30 }" @click="switchPeriod(30)">近30天</view>
      <view class="period-item" :class="{ active: period === 90 }" @click="switchPeriod(90)">近90天</view>
    </view>

    <view class="card animate-in">
      <view class="card-header">
        <text class="card-title">{{ tabName }}趋势</text>
      </view>
      <view class="chart-container">
        <canvas canvas-id="trendChart" id="trendChart" class="chart-canvas"></canvas>
      </view>
    </view>

    <view class="card animate-in" style="animation-delay:.1s">
      <view class="card-header">
        <text class="card-title">{{ tabName }}统计</text>
      </view>
      <view class="stats-grid">
        <view class="stats-item">
          <text class="stats-label">平均值</text>
          <text class="stats-value">{{ stats.avg || '--' }}</text>
        </view>
        <view class="stats-item">
          <text class="stats-label">最高值</text>
          <text class="stats-value">{{ stats.max || '--' }}</text>
        </view>
        <view class="stats-item">
          <text class="stats-label">最低值</text>
          <text class="stats-value">{{ stats.min || '--' }}</text>
        </view>
        <view class="stats-item">
          <text class="stats-label">记录次数</text>
          <text class="stats-value">{{ stats.count || 0 }}</text>
        </view>
      </view>
    </view>

    <view class="card animate-in" style="animation-delay:.2s">
      <view class="card-header">
        <text class="card-title">详细记录</text>
      </view>
      <view class="detail-item" v-for="(item, index) in detailList" :key="index">
        <view class="detail-left">
          <text class="detail-date">{{ formatDate(item.measure_time) }}</text>
          <text class="detail-value">{{ getDisplayValue(item) }}</text>
        </view>
        <text class="detail-status" :class="getValueClass(item)">{{ getValueLabel(item) }}</text>
      </view>
      <view class="empty-tip" v-if="detailList.length === 0">暂无数据</view>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  data() {
    return { activeTab: 'glucose', period: 7, stats: {}, detailList: [], chartData: [] }
  },
  computed: {
    tabName() {
      const map = { 'glucose': '血糖', 'blood_pressure': '血压', 'weight': '体重', 'waist': '腰围' }
      return map[this.activeTab] || ''
    }
  },
  onShow() {
    if (uni.getStorageSync('token')) {
      this.loadData()
    }
  },
  methods: {
    switchTab(tab) {
      this.activeTab = tab
      if (uni.getStorageSync('token')) {
        this.loadData()
      }
    },
    switchPeriod(p) { this.period = p; this.loadData() },
    async loadData() {
      try {
        const res = await api.getStats({ type: this.activeTab, period: this.period })
        this.stats = res.stats || {}
        this.detailList = res.list || []
        this.chartData = res.chart || []
        this.$nextTick(() => this.drawChart())
      } catch (e) { console.error(e) }
    },
    drawChart() {
      const ctx = uni.createCanvasContext('trendChart', this)
      const data = this.chartData
      if (!data || data.length === 0) {
        ctx.setFontSize(14); ctx.setFillStyle('#ccc'); ctx.setTextAlign('center')
        ctx.fillText('暂无数据', 175, 100); ctx.draw(); return
      }
      const canvasW = 350, canvasH = 200, padL = 40, padR = 20, padT = 20, padB = 30
      const chartW = canvasW - padL - padR, chartH = canvasH - padT - padB
      const values = data.map(d => parseFloat(d.value || d.weight || d.systolic || 0))
      const maxVal = Math.max(...values), minVal = Math.min(...values), range = maxVal - minVal || 1

      ctx.setStrokeStyle('#20bf6b'); ctx.setLineWidth(2.5); ctx.beginPath()
      data.forEach((item, i) => {
        const x = padL + (i / (data.length - 1 || 1)) * chartW
        const val = parseFloat(item.value || item.weight || item.systolic || 0)
        const y = padT + chartH - ((val - minVal) / range) * chartH
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y)
      })
      ctx.stroke()

      data.forEach((item, i) => {
        const x = padL + (i / (data.length - 1 || 1)) * chartW
        const val = parseFloat(item.value || item.weight || item.systolic || 0)
        const y = padT + chartH - ((val - minVal) / range) * chartH
        ctx.beginPath(); ctx.arc(x, y, 4, 0, 2 * Math.PI)
        ctx.setFillStyle('#20bf6b'); ctx.fill()
        ctx.beginPath(); ctx.arc(x, y, 6, 0, 2 * Math.PI)
        ctx.setFillStyle('rgba(32,191,107,0.15)'); ctx.fill()
      })

      ctx.setFontSize(10); ctx.setFillStyle('#999'); ctx.setTextAlign('right')
      ctx.fillText(maxVal.toFixed(1), padL - 5, padT + 5)
      ctx.fillText(minVal.toFixed(1), padL - 5, padT + chartH + 5)
      ctx.setTextAlign('center')
      if (data.length > 0) {
        ctx.fillText(this.formatDateShort(data[0].measure_time), padL, canvasH - 5)
        ctx.fillText(this.formatDateShort(data[data.length - 1].measure_time), padL + chartW, canvasH - 5)
      }
      ctx.draw()
    },
    getDisplayValue(item) {
      if (this.activeTab === 'blood_pressure') return `${item.systolic}/${item.diastolic} mmHg`
      if (this.activeTab === 'glucose') return `${item.value} mmol/L`
      if (this.activeTab === 'weight') return `${item.weight} kg`
      if (this.activeTab === 'waist') return `${item.value} cm`
      return ''
    },
    getValueLabel(item) {
      if (this.activeTab === 'glucose') {
        const v = parseFloat(item.value)
        if (v < 3.9) return '偏低'; if (v > 11.1) return '偏高'; return '正常'
      }
      if (this.activeTab === 'blood_pressure') {
        const s = parseInt(item.systolic), d = parseInt(item.diastolic)
        if (s >= 140 || d >= 90) return '偏高'; if (s < 90 || d < 60) return '偏低'; return '正常'
      }
      return ''
    },
    getValueClass(item) {
      const l = this.getValueLabel(item)
      if (l === '偏高') return 'status-high'; if (l === '偏低') return 'status-low'; return 'status-normal'
    },
    formatDate(t) {
      if (!t) return ''; const d = new Date(t * 1000)
      return `${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
    },
    formatDateShort(t) {
      if (!t) return ''; const d = new Date(t * 1000)
      return `${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getDate()).padStart(2,'0')}`
    }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background: #f5f6fa; padding-bottom: 30px; }

.custom-nav { background: linear-gradient(135deg, #20bf6b, #0fb9b1); padding-top: var(--status-bar-height); padding-bottom: 14px; border-radius: 0 0 24rpx 24rpx; }
.nav-content { padding: 12px 20px 0; }
.nav-title { font-size: 20px; font-weight: bold; color: #fff; }

.tab-bar {
  display: flex; background: #fff; padding: 0 10px; margin-bottom: 10px;
}
.tab-item {
  flex: 1; text-align: center; padding: 14px 0; font-size: 13px; color: #888;
  border-bottom: 2.5px solid transparent; transition: all .3s;
}
.tab-item.active { color: #20bf6b; border-bottom-color: #20bf6b; font-weight: bold; }

.period-bar {
  display: flex; padding: 0 16px; margin-bottom: 16px; gap: 10px;
}
.period-item {
  padding: 8px 20px; border-radius: 20px; background: #fff; font-size: 13px; color: #666;
  box-shadow: 0 1px 4px rgba(0,0,0,0.02); transition: all .3s;
}
.period-item.active { background: #20bf6b; color: #fff; box-shadow: 0 4px 12px rgba(32,191,107,0.3); }

.animate-in { animation: fadeUp .45s ease both; }
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

.card {
  margin: 0 16px 14px; background: #fff; border-radius: 20px; padding: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.card-header { margin-bottom: 16px; }
.card-title { font-size: 16px; font-weight: bold; color: #1a1a2e; }

.chart-container { width: 100%; }
.chart-canvas { width: 350px; height: 200px; }

.stats-grid { display: flex; flex-wrap: wrap; }
.stats-item { width: 50%; padding: 12px 0; box-sizing: border-box; }
.stats-label { font-size: 13px; color: #aaa; display: block; margin-bottom: 6px; }
.stats-value { font-size: 22px; font-weight: bold; color: #1a1a2e; }

.detail-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 0; border-bottom: 1px solid #f5f6fa;
}
.detail-item:last-child { border: none; }
.detail-left { display: flex; flex-direction: column; }
.detail-date { font-size: 12px; color: #aaa; margin-bottom: 4px; }
.detail-value { font-size: 15px; font-weight: bold; color: #1a1a2e; }
.detail-status { font-size: 12px; padding: 4px 12px; border-radius: 20px; font-weight: 500; }
.status-normal { background: #e8f8f0; color: #20bf6b; }
.status-high { background: #ffeaea; color: #ff4757; }
.status-low { background: #fff4e6; color: #ff9800; }

.empty-tip { text-align: center; padding: 30px 0; color: #ccc; font-size: 14px; }
</style>
