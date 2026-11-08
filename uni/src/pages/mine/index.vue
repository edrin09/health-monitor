<!--
页面路径: /pages/mine/index
页面功能: 我的 - 用户中心
显示用户头像/昵称/手机号，提供健康档案、血糖/血压/体重/腰围记录入口，退出登录
-->
<template>
  <view class="container">
    <view class="custom-nav">
      <view class="nav-content">
        <text class="nav-title">我的</text>
      </view>
    </view>

    <view class="user-card" @click="onUserCardClick">
      <view class="user-avatar">
        <image :src="userInfo.avatar || '/static/logo.png'" mode="aspectFill"></image>
        <view class="avatar-ring"></view>
      </view>
      <view class="user-text">
        <text class="nickname">{{ userInfo.nickname || '微信用户' }}</text>
        <text class="phone login-hint" v-if="!hasToken">点击登录</text>
      </view>
      <view class="user-arrow">›</view>
    </view>

    <view class="menu-card">
      <view class="menu-item" @click="goPage('profile')">
        <view class="menu-icon-box icon-green">
          <text>📋</text>
        </view>
        <text class="menu-label">健康档案</text>
        <text class="menu-arrow">›</text>
      </view>

      <view class="menu-item" @click="goPage('glucose')">
        <view class="menu-icon-box icon-red">
          <text>🩸</text>
        </view>
        <text class="menu-label">血糖记录</text>
        <text class="menu-arrow">›</text>
      </view>

      <view class="menu-item" @click="goPage('blood-pressure')">
        <view class="menu-icon-box icon-blue">
          <text>💗</text>
        </view>
        <text class="menu-label">血压记录</text>
        <text class="menu-arrow">›</text>
      </view>

      <view class="menu-item" @click="goPage('weight')">
        <view class="menu-icon-box icon-purple">
          <text>⚖️</text>
        </view>
        <text class="menu-label">体重记录</text>
        <text class="menu-arrow">›</text>
      </view>

      <view class="menu-item" @click="goPage('waist')">
        <view class="menu-icon-box icon-orange">
          <text>📏</text>
        </view>
        <text class="menu-label">腰围记录</text>
        <text class="menu-arrow">›</text>
      </view>
    </view>

    <view class="menu-card">
      <view class="menu-item" @click="showAbout">
        <view class="menu-icon-box icon-gray">
          <text>ℹ️</text>
        </view>
        <text class="menu-label">关于</text>
        <text class="menu-arrow">›</text>
      </view>
    </view>

    <view class="menu-card" v-if="hasToken">
      <view class="menu-item logout-item" @click="doLogout">
        <text class="logout-text">退出登录</text>
      </view>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  data() {
    return {
      userInfo: {}
    }
  },
  computed: {
    hasToken() {
      return !!uni.getStorageSync('token')
    }
  },
  onShow() {
    this.loadUserInfo()
    this.refreshUserInfo()
  },
  methods: {
    loadUserInfo() {
      const info = uni.getStorageSync('userInfo')
      if (info) {
        this.userInfo = info
      }
    },
    refreshUserInfo() {
      if (!this.hasToken) return
      api.getUserInfo().then(res => {
        if (res) {
          const data = res.customer_expansion || res
          this.userInfo = {
            customer_id: data.customer_id || '',
            nickname: data.nickname || '',
            avatar: data.avatar || '',
            phone: data.phone_number || ''
          }
          uni.setStorageSync('userInfo', this.userInfo)
        }
      }).catch(e => {
        console.error('刷新用户信息失败', e)
      })
    },
    onUserCardClick() {
      if (!this.hasToken) {
        uni.navigateTo({ url: '/pages/login/index' })
      }
    },
    doLogout() {
      uni.showModal({
        title: '提示',
        content: '确定要退出登录吗？',
        success: (res) => {
          if (res.confirm) {
            uni.removeStorageSync('token')
            uni.removeStorageSync('userInfo')
            this.userInfo = {}
            uni.showToast({ title: '已退出登录', icon: 'success' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/index/index' })
            }, 1000)
          }
        }
      })
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
      uni.navigateTo({ url: `/pages/${path}/index` })
    },
    showAbout() {
      uni.showModal({
        title: '关于',
        content: '健康监测 v1.0.0\n记录您的健康数据，守护您的健康',
        showCancel: false
      })
    }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background-color: #f5f6fa; font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', 'Noto Sans SC', sans-serif; }

.user-card {
  margin: 16px 16px 0;
  background: linear-gradient(135deg, #20bf6b, #0fb9b1, #0984e3);
  border-radius: 20px;
  padding: 24px 20px;
  display: flex;
  align-items: center;
  position: relative;
  overflow: hidden;
  animation: fadeIn .5s ease;
}

.user-card::before {
  content: '';
  position: absolute;
  top: -50%; right: -30%;
  width: 200px; height: 200px;
  background: rgba(255,255,255,0.08);
  border-radius: 50%;
}

.user-avatar {
  position: relative;
  margin-right: 16px;
}

.user-avatar image {
  width: 60px; height: 60px;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
  position: relative;
  z-index: 1;
}

.avatar-ring {
  position: absolute;
  top: -4px; left: -4px;
  width: 68px; height: 68px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.3);
}

.user-text {
  flex: 1;
  position: relative;
  z-index: 1;
}

.nickname {
  font-size: 20px;
  font-weight: bold;
  color: #fff;
  display: block;
  margin-bottom: 4px;
}

.phone {
  font-size: 13px;
  color: rgba(255,255,255,0.8);
}

.user-arrow {
  font-size: 24px;
  color: rgba(255,255,255,0.5);
  position: relative;
  z-index: 1;
}

.menu-card {
  margin: 16px;
  background: #fff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.03);
  animation: fadeIn .5s ease .1s both;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #f5f5f5;
  transition: background .2s;
}

.menu-item:active {
  background: #fafafa;
}

.menu-item:last-child { border-bottom: none; }

.menu-icon-box {
  width: 36px; height: 36px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-right: 14px;
  font-size: 18px;
}

.icon-green { background: #e8f8f0; }
.icon-red { background: #ffeaea; }
.icon-blue { background: #e8f4fd; }
.icon-purple { background: #f3eaff; }
.icon-orange { background: #fff4e6; }
.icon-gray { background: #f5f6fa; }

.menu-label {
  flex: 1;
  font-size: 15px;
  color: #333;
}

.menu-arrow {
  font-size: 18px;
  color: #ccc;
}

.logout-item { justify-content: center; }
.logout-text {
  font-size: 15px;
  color: #ff4757;
  font-weight: 500;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
