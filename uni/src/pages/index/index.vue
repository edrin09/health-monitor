<!--
页面路径: /pages/index/index
页面功能: 首页仪表盘
显示内容: BMR基础代谢率、四项健康指标最新值、最近记录列表、语音录入浮动按钮
-->
<template>
  <view class="container">
    <view class="custom-nav">
      <view class="nav-content">
        <text class="nav-title">健康监测</text>
      </view>
    </view>

    <view class="bmr-card">
      <view class="bmr-header">
        <text class="bmr-label">基础代谢率 (BMR)</text>
        <image class="fire-icon" src="/static/icons/fire.png" mode="aspectFit"></image>
      </view>
      <view class="bmr-body">
        <text class="bmr-value">{{ dashboard.bmr || '--' }}</text>
        <text class="bmr-unit">kcal</text>
      </view>
      <text class="bmr-desc">基于最新体重与个人信息计算</text>
    </view>

    <view class="grid-container">
      <view class="grid-item" @click="checkLogin('glucose')">
        <view class="icon-box red-bg">
          <text class="icon-text">🩸</text>
        </view>
        <view class="info">
          <text class="label">血糖</text>
          <view class="val-row">
            <text class="value">{{ dashboard.latest_glucose ? dashboard.latest_glucose.value : '--' }}</text>
            <text class="unit">mmol/L</text>
          </view>
        </view>
        <text class="tag">最近</text>
      </view>

      <view class="grid-item" @click="checkLogin('blood-pressure')">
        <view class="icon-box blue-bg">
          <text class="icon-text">💗</text>
        </view>
        <view class="info">
          <text class="label">血压</text>
          <view class="val-row">
            <text class="value">{{ dashboard.latest_blood_pressure ? dashboard.latest_blood_pressure.systolic + '/' + dashboard.latest_blood_pressure.diastolic : '--/--' }}</text>
            <text class="unit">mmHg</text>
          </view>
        </view>
        <text class="tag">最近</text>
      </view>

      <view class="grid-item" @click="checkLogin('weight')">
        <view class="icon-box purple-bg">
          <text class="icon-text">⚖️</text>
        </view>
        <view class="info">
          <text class="label">体重</text>
          <view class="val-row">
            <text class="value">{{ dashboard.latest_weight ? dashboard.latest_weight.weight : '--' }}</text>
            <text class="unit">kg</text>
          </view>
        </view>
        <text class="tag">最近</text>
      </view>

      <view class="grid-item" @click="checkLogin('waist')">
        <view class="icon-box orange-bg">
          <text class="icon-text">📏</text>
        </view>
        <view class="info">
          <text class="label">腰围</text>
          <view class="val-row">
            <text class="value">{{ dashboard.latest_waist ? dashboard.latest_waist.value : '--' }}</text>
            <text class="unit">cm</text>
          </view>
        </view>
        <text class="tag">最近</text>
      </view>
    </view>

    <view class="section-title">最近记录</view>
    <view class="record-list">
      <view class="record-item" v-for="(item, index) in dashboard.records" :key="index">
        <view class="record-left">
          <view class="record-icon" :class="'bg-' + item.color">
            <text>{{ item.icon }}</text>
          </view>
          <view class="record-info">
            <text class="record-val">{{ item.value }} <text class="record-unit">{{ item.unit }}</text></text>
            <text class="record-time">{{ formatTime(item.measure_time) }} · {{ item.measure_status_text || getTypeName(item.type) }}</text>
          </view>
        </view>
      </view>
      <view class="empty-tip" v-if="!dashboard.records || dashboard.records.length === 0">
        <text>暂无记录，快去录入健康数据吧~</text>
      </view>
    </view>

    <view class="fab" @click="showVoice = true" v-if="hasToken && voiceEnabled">
      <text class="fab-icon">🎤</text>
    </view>

    <VoiceInput v-if="voiceEnabled" :visible="showVoice" @close="showVoice = false" @submitted="onVoiceSubmitted" />
  </view>
</template>

<script>
import { api } from '@/utils/api.js'
import VoiceInput from '@/components/VoiceInput.vue'

export default {
  components: { VoiceInput },
  data() {
    return {
      dashboard: {},
      userInfo: {},
      showVoice: false
    }
  },
  computed: {
    hasToken() {
      return !!uni.getStorageSync('token')
    },
    voiceEnabled() {
      return import.meta.env.VITE_VOICE_INPUT_ENABLED !== 'false'
    }
  },
  onShow() {
    this.loadUserInfo()
    this.loadDashboard()
  },
  methods: {
    checkLogin(path) {
      if (!this.hasToken) {
        uni.showModal({
          title: '提示',
          content: '请先登录后再查看',
          confirmText: '去登录',
          cancelText: '暂不',
          success: (res) => {
            if (res.confirm) {
              uni.navigateTo({ url: '/pages/login/index' })
            }
          }
        })
        return
      }
      this.goPage(path)
    },
    getTypeName(type) {
      const map = {
        'glucose': '血糖',
        'blood_pressure': '血压',
        'weight': '体重',
        'waist': '腰围'
      }
      return map[type] || ''
    },
    formatTime(timestamp) {
      if (!timestamp) return ''
      const date = new Date(timestamp * 1000)
      const y = date.getFullYear()
      const m = String(date.getMonth() + 1).padStart(2, '0')
      const d = String(date.getDate()).padStart(2, '0')
      const h = String(date.getHours()).padStart(2, '0')
      const min = String(date.getMinutes()).padStart(2, '0')
      return `${y}-${m}-${d} ${h}:${min}`
    },
    async loadDashboard() {
      try {
        const res = await api.getDashboard()
        this.dashboard = res || {}
      } catch (e) {
        console.error('获取首页数据失败', e)
      }
    },
    loadUserInfo() {
      const info = uni.getStorageSync('userInfo')
      if (info) {
        this.userInfo = info
      }
    },
    goPage(path) {
      const needLoginPaths = ['glucose', 'blood-pressure', 'weight', 'waist', 'profile']
      if (needLoginPaths.includes(path) && !this.hasToken) {
        uni.showModal({
          title: '提示',
          content: '请先登录后再查看',
          confirmText: '去登录',
          cancelText: '暂不',
          success: (res) => {
            if (res.confirm) {
              uni.navigateTo({ url: '/pages/login/index' })
            }
          }
        })
        return
      }
      uni.navigateTo({
        url: `/pages/${path}/index`,
        fail: () => {
          uni.switchTab({
            url: `/pages/${path}/index`
          })
        }
      })
    },
    goLogin() {
      uni.navigateTo({
        url: '/pages/login/index'
      })
    },
    onVoiceSubmitted() {
      this.loadDashboard()
    }
  }
}
</script>

<style lang="scss" scoped>
.container {
  min-height: 100vh;
  background-color: #f7f8fa;
  padding-bottom: 40px;
}

.custom-nav {
  background: linear-gradient(135deg, #20bf6b, #0fb9b1);
  padding-top: var(--status-bar-height);
  padding-bottom: 14px;
  border-radius: 0 0 24rpx 24rpx;
}
.nav-content {
  display: flex;
  align-items: center;
  padding: 12px 20px 0;
}
.nav-title {
  font-size: 20px;
  font-weight: bold;
  color: #fff;
}

.bmr-card {
  margin: 15px 20px;
  background: linear-gradient(135deg, #20bf6b, #0fb9b1);
  border-radius: 16px;
  padding: 20px;
  color: #fff;
  box-shadow: 0 4px 10px rgba(32, 191, 107, 0.3);
}
.bmr-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.bmr-label {
  font-size: 14px;
  opacity: 0.9;
}
.fire-icon {
  width: 20px;
  height: 20px;
}
.bmr-body {
  display: flex;
  align-items: baseline;
  margin-bottom: 5px;
}
.bmr-value {
  font-size: 36px;
  font-weight: bold;
  margin-right: 5px;
}
.bmr-unit {
  font-size: 16px;
}
.bmr-desc {
  font-size: 12px;
  opacity: 0.8;
}

.grid-container {
  display: flex;
  flex-wrap: wrap;
  padding: 0 15px;
  justify-content: space-between;
}
.grid-item {
  width: 48%;
  background-color: #fff;
  border-radius: 12px;
  padding: 15px;
  margin-bottom: 15px;
  box-sizing: border-box;
  position: relative;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
}
.icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  font-size: 18px;
}
.red-bg { background-color: #ffeaea; color: #ff4757; }
.blue-bg { background-color: #eaf6ff; color: #2ed573; }
.purple-bg { background-color: #f3eaff; color: #a55eea; }
.orange-bg { background-color: #fff4e6; color: #ffa502; }

.info {
  flex: 1;
}
.label {
  font-size: 14px;
  color: #666;
  display: block;
  margin-bottom: 5px;
}
.val-row {
  display: flex;
  align-items: baseline;
}
.value {
  font-size: 20px;
  font-weight: bold;
  color: #333;
}
.unit {
  font-size: 12px;
  color: #999;
  margin-left: 2px;
}
.tag {
  position: absolute;
  top: 15px;
  right: 15px;
  font-size: 12px;
  color: #ccc;
}

.section-title {
  padding: 10px 20px;
  font-size: 16px;
  font-weight: bold;
  color: #333;
}
.record-list {
  padding: 0 20px;
}
.record-item {
  background-color: #fff;
  padding: 15px;
  border-radius: 12px;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.record-left {
  display: flex;
  align-items: center;
}
.record-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  font-size: 18px;
}
.bg-red { background-color: #ffeaea; }
.bg-orange { background-color: #fff4e6; }
.bg-purple { background-color: #f3eaff; }
.bg-blue { background-color: #eaf6ff; }

.record-info {
  display: flex;
  flex-direction: column;
}
.record-val {
  font-size: 16px;
  font-weight: bold;
  color: #333;
  margin-bottom: 4px;
}
.record-unit {
  font-size: 12px;
  font-weight: normal;
  color: #666;
}
.record-time {
  font-size: 12px;
  color: #999;
}
.arrow {
  font-size: 20px;
  color: #ccc;
  font-weight: bold;
}
.empty-tip {
  text-align: center;
  padding: 40px 0;
  color: #ccc;
  font-size: 14px;
}

.fab {
  position: fixed; bottom: 80px; right: 20px; z-index: 99;
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(135deg, #20bf6b, #0fb9b1);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(32, 191, 107, 0.4);
  animation: fabIn .5s ease;
}
.fab-icon { font-size: 24px; }

@keyframes fabIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>
