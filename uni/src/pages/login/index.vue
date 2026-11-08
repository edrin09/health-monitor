<template>
  <view class="login-container">
    <view class="login-header">
      <view class="logo-wrapper">
        <image class="logo" src="/static/logo.png" mode="aspectFit"></image>
      </view>
      <text class="app-name">健康监测</text>
      <text class="app-desc">记录您的健康数据，守护您的健康</text>
    </view>

    <view class="login-card" v-if="isH5">
      <view class="card-inner">
        <text class="card-title">账号登录</text>
        <view class="input-group">
          <view class="input-icon">📱</view>
          <input class="input-field" type="text" v-model="phone" placeholder="请输入手机号" maxlength="11" @input="onPhoneInput" />
        </view>
        <view class="input-group">
          <view class="input-icon">🔒</view>
          <input class="input-field" type="password" v-model="password" placeholder="请输入密码" @confirm="doH5Login" />
        </view>
        <view class="agreement-row">
          <label class="checkbox-label" @click="agree = !agree">
            <text class="checkbox-icon" :class="{ checked: agree }">✓</text>
            <text class="agree-text">已阅读并同意</text>
          </label>
          <text class="agreement-link" @click="goAgreement('user_agreement')">《用户服务协议》</text>
          <text class="agree-text">和</text>
          <text class="agreement-link" @click="goAgreement('privacy_policy')">《隐私政策》</text>
        </view>
        <button class="login-btn" @click="doH5Login" :disabled="isLogging || !agree">
          <text class="btn-text">{{ isLogging ? '登录中...' : '登 录' }}</text>
        </button>
        <view class="cancel-row" @click="goHome">
          <text class="cancel-text">暂不登录，先看看</text>
        </view>
      </view>
    </view>

    <view class="login-card" v-else>
      <view class="card-inner">
        <button class="login-btn wechat-btn" open-type="getUserInfo" @getuserinfo="onGetUserInfo" v-if="canUseGetUserInfo">
          <text class="btn-icon">💬</text>
          <text class="btn-text">微信授权登录</text>
        </button>
        <button class="login-btn wechat-btn" @click="doWechatLogin" v-else>
          <text class="btn-icon">💬</text>
          <text class="btn-text">微信一键登录</text>
        </button>

        <view class="agreement-row">
          <label class="checkbox-label" @click="agree = !agree">
            <text class="checkbox-icon" :class="{ checked: agree }">✓</text>
            <text class="agree-text">已阅读并同意</text>
          </label>
          <text class="agreement-link" @click="goAgreement('user_agreement')">《用户服务协议》</text>
          <text class="agree-text">和</text>
          <text class="agreement-link" @click="goAgreement('privacy_policy')">《隐私政策》</text>
        </view>
        <view class="cancel-row" @click="goHome">
          <text class="cancel-text">暂不登录</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  data() {
    return {
      isH5: true,
      canUseGetUserInfo: true,
      isLogging: false,
      agree: false,
      phone: '',
      password: ''
    }
  },
  onLoad() {
    this.checkPlatform()
  },
  methods: {
    checkPlatform() {
      // #ifdef H5
      this.isH5 = true
      // #endif
      // #ifdef MP-WEIXIN
      this.isH5 = false
      this.checkCanUseGetUserInfo()
      // #endif
    },
    checkCanUseGetUserInfo() {
      try {
        const systemInfo = uni.getSystemInfoSync()
        const version = systemInfo.SDKVersion
        const canUse = this.compareVersion(version, '2.21.2') < 0
        this.canUseGetUserInfo = canUse
      } catch (e) {
        this.canUseGetUserInfo = false
      }
    },
    compareVersion(v1, v2) {
      const v1Parts = v1.split('.')
      const v2Parts = v2.split('.')
      const len = Math.max(v1Parts.length, v2Parts.length)
      for (let i = 0; i < len; i++) {
        const a = parseInt(v1Parts[i] || '0')
        const b = parseInt(v2Parts[i] || '0')
        if (a > b) return 1
        if (a < b) return -1
      }
      return 0
    },
    goAgreement(type) {
      uni.navigateTo({
        url: '/pages/agreement/index?type=' + type
      })
    },
    goHome() {
      const pages = getCurrentPages()
      if (pages.length > 1) {
        uni.navigateBack()
      } else {
        uni.switchTab({ url: '/pages/index/index' })
      }
    },
    onPhoneInput(e) {
      this.phone = e.target.value.replace(/\D/g, '')
    },
    async doH5Login() {
      if (this.isLogging) return
      if (!this.agree) {
        uni.showToast({ title: '请先阅读并同意协议', icon: 'none' })
        return
      }
      if (!this.phone || this.phone.length < 11) {
        uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
        return
      }
      if (!this.password) {
        uni.showToast({ title: '请输入密码', icon: 'none' })
        return
      }

      this.isLogging = true
      try {
        const res = await api.h5Login(this.phone, this.password)
        if (res) {
          const token = res.token || ''
          uni.setStorageSync('token', token)
          uni.setStorageSync('userInfo', {
            customer_id: res.customer_id || '',
            nickname: res.nickname || '',
            avatar: res.avatar || '',
            phone: res.phone_number || ''
          })
          uni.showToast({ title: '登录成功', icon: 'success' })
          setTimeout(() => {
            uni.reLaunch({ url: '/pages/index/index' })
          }, 1000)
        }
      } catch (e) {
        console.error('H5登录失败', e)
      } finally {
        this.isLogging = false
      }
    },
    onGetUserInfo(e) {
      if (e.detail.errMsg !== 'getUserInfo:ok') {
        uni.showToast({ title: '需要授权才能登录', icon: 'none' })
        return
      }
      if (!this.agree) {
        uni.showToast({ title: '请先阅读并同意协议', icon: 'none' })
        return
      }
      const userInfo = e.detail.userInfo
      this.doWechatLogin(userInfo)
    },
    async doWechatLogin(userInfo = {}) {
      if (this.isLogging) return
      if (!this.agree) {
        uni.showToast({ title: '请先阅读并同意协议', icon: 'none' })
        return
      }
      this.isLogging = true

      try {
        const loginRes = await new Promise((resolve, reject) => {
          uni.login({
            provider: 'weixin',
            success: resolve,
            fail: reject
          })
        })

        if (!loginRes.code) {
          uni.showToast({ title: '获取登录凭证失败', icon: 'none' })
          return
        }

        const res = await api.login(loginRes.code, {
          avatar: userInfo.avatarUrl || '',
          nickname: userInfo.nickName || ''
        })

        if (res && res.user_info) {
          const info = res.user_info
          const token = info.expansion ? info.expansion.token : ''
          uni.setStorageSync('token', token)
          uni.setStorageSync('userInfo', {
            customer_id: info.customer_id || '',
            nickname: info.nickname || '',
            avatar: info.avatar || '',
            phone: info.phone_number || ''
          })

          uni.showToast({ title: '登录成功', icon: 'success' })

          setTimeout(() => {
            uni.reLaunch({ url: '/pages/index/index' })
          }, 1000)
        }
      } catch (e) {
        console.error('微信登录失败', e)
      } finally {
        this.isLogging = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.login-container {
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', 'Noto Sans SC', sans-serif;
  background: linear-gradient(160deg, #20bf6b 0%, #0fb9b1 50%, #0984e3 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 30px;
  position: relative;
  overflow: hidden;
}

.login-container::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
  animation: bgFloat 20s ease-in-out infinite;
}

@keyframes bgFloat {
  0%, 100% { transform: translate(0, 0); }
  50% { transform: translate(-5%, 5%); }
}

.login-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 50px;
  animation: fadeInDown .6s ease;
  position: relative;
  z-index: 1;
}

@keyframes fadeInDown {
  from { transform: translateY(-20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.logo-wrapper {
  width: 90px; height: 90px; border-radius: 24px;
  background: rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px;
  backdrop-filter: blur(10px);
}

.logo {
  width: 60px; height: 60px;
}

.app-name {
  font-size: 28px;
  font-weight: bold;
  color: #fff;
  margin-bottom: 8px;
  text-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.app-desc {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
}

.login-card {
  width: 100%;
  background-color: #fff;
  border-radius: 24px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  animation: fadeInUp .6s ease .2s both;
  position: relative;
  z-index: 1;
}

@keyframes fadeInUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.card-inner {
  padding: 32px 24px;
}

.card-title {
  display: block;
  font-size: 20px;
  font-weight: bold;
  color: #1a1a2e;
  margin-bottom: 28px;
  text-align: center;
}

.input-group {
  display: flex;
  align-items: center;
  height: 52px;
  border: 1.5px solid #e8e8e8;
  border-radius: 14px;
  margin-bottom: 16px;
  padding: 0 14px;
  background: #fafafa;
  transition: all .3s;

  &:focus-within {
    border-color: #20bf6b;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(32, 191, 107, 0.08);
  }
}

.input-icon {
  font-size: 18px;
  margin-right: 10px;
}

.input-field {
  flex: 1;
  height: 100%;
  border: none;
  font-size: 16px;
  background: transparent;
  outline: none;
}

.login-btn {
  width: 100%;
  height: 50px;
  background: linear-gradient(135deg, #20bf6b, #0fb9b1);
  border-radius: 25px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  margin-top: 24px;
  transition: all .3s;
  box-shadow: 0 4px 15px rgba(32, 191, 107, 0.3);
}

.login-btn:active {
  transform: scale(0.97);
}

.login-btn[disabled] {
  opacity: 0.6;
}

.login-btn::after {
  border: none;
}

.btn-icon {
  font-size: 20px;
  margin-right: 8px;
}

.btn-text {
  font-size: 17px;
  font-weight: bold;
  color: #fff;
  letter-spacing: 2px;
}

.wechat-btn {
  background: linear-gradient(135deg, #07c160, #06ad56);
  box-shadow: 0 4px 15px rgba(7, 193, 96, 0.3);
}

.agreement-row {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 20px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  margin-right: 4px;
}

.checkbox-icon {
  width: 32rpx;
  height: 32rpx;
  border: 2rpx solid #ccc;
  border-radius: 6rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20rpx;
  color: transparent;
  margin-right: 6px;
  transition: all .2s;
}

.checkbox-icon.checked {
  background: #20bf6b;
  border-color: #20bf6b;
  color: #fff;
}

.agree-text {
  font-size: 12px;
  color: #999;
}

.agreement-link {
  font-size: 12px;
  color: #20bf6b;
}

.cancel-row {
  text-align: center;
  margin-top: 16px;
  padding: 8px 0;
}

.cancel-text {
  font-size: 13px;
  color: #999;
}
</style>