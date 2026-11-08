<!--
页面路径: /pages/profile/index
页面功能: 健康档案 - 编辑昵称/性别/出生日期/身高，保存后同步到首页BMR计算
-->
<template>
  <view class="container page-record">
    <view class="form-card animate-in">
      <view class="card-header">
        <view class="header-icon green-glow">📋</view>
        <text class="title">健康档案</text>
        <text class="header-desc">完善个人信息，获取更准确的健康分析</text>
      </view>

      <view class="form-body">
        <view class="input-group">
          <text class="label">昵称</text>
          <view class="input-wrap">
            <text class="input-prefix">👤</text>
            <input placeholder="请输入昵称" class="input-box" v-model="form.nickname" />
          </view>
        </view>

        <view class="input-group">
          <text class="label">性别</text>
          <picker mode="selector" :range="genderOptions" @change="onGenderChange">
            <view class="picker-box">
              <text>{{ genderOptions[form.gender] }}</text>
              <text class="picker-arrow">▾</text>
            </view>
          </picker>
        </view>

        <view class="input-group">
          <text class="label">出生日期</text>
          <picker mode="date" :value="form.birth_date" :end="todayStr" @change="onBirthChange">
            <view class="picker-box">
              <text>{{ form.birth_date || '请选择' }}</text>
              <text class="picker-arrow">▾</text>
            </view>
          </picker>
        </view>

        <view class="input-group">
          <text class="label">身高</text>
          <view class="input-wrap">
            <input type="digit" placeholder="170" class="input-box" v-model="form.height" />
            <text class="input-unit">cm</text>
          </view>
        </view>
      </view>

      <button class="submit-btn" @click="submitData" :disabled="loading">
        <text>{{ loading ? '保存中...' : '保存档案' }}</text>
      </button>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  data() {
    return {
      form: { nickname: '', gender: 0, birth_date: '', height: '' },
      genderOptions: ['女', '男'],
      loading: false
    }
  },
  computed: {
    todayStr() {
      const d = new Date()
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }
  },
  onLoad() {
    if (!uni.getStorageSync('token')) {
      uni.showToast({ title: '请先登录', icon: 'none' })
      setTimeout(() => { uni.reLaunch({ url: '/pages/login/index' }) }, 1000)
      return
    }
    this.loadProfile()
  },
  methods: {
    async loadProfile() {
      try {
        const res = await api.getProfile()
        if (res.profile) {
          this.form.nickname = res.profile.nickname || ''
          this.form.gender = res.profile.gender || 0
          this.form.birth_date = res.profile.birth_date || ''
          this.form.height = res.profile.height || ''
        }
      } catch (e) { console.error(e) }
    },
    onGenderChange(e) { this.form.gender = parseInt(e.detail.value) },
    onBirthChange(e) { this.form.birth_date = e.detail.value },
    async submitData() {
      this.loading = true
      try {
        await api.updateProfile({ nickname: this.form.nickname, gender: this.form.gender, birth_date: this.form.birth_date, height: this.form.height ? parseFloat(this.form.height) : 0 })
        uni.showToast({ title: '保存成功', icon: 'success' })
        const userInfo = uni.getStorageSync('userInfo') || {}
        userInfo.nickname = this.form.nickname
        uni.setStorageSync('userInfo', userInfo)
      } catch (e) { console.error(e)
      } finally { this.loading = false }
    }
  }
}
</script>

<style lang="scss">
.page-record {
  padding: 16px; background: #f5f6fa; min-height: 100vh;
}

.animate-in {
  animation: fadeUp .45s ease both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

.form-card {
  background: #fff; border-radius: 20px; padding: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px;

  .card-header {
    margin-bottom: 24px;

    .header-icon {
      width: 44px; height: 44px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; margin-bottom: 12px;
    }
    .green-glow { background: #e8f8f0; box-shadow: 0 4px 12px rgba(32,191,107,0.15); }
    .title { font-size: 18px; font-weight: bold; color: #1a1a2e; display: block; margin-bottom: 6px; }
    .header-desc { font-size: 13px; color: #999; display: block; }
  }

  .form-body { margin-bottom: 8px; }

  .input-group {
    margin-bottom: 18px;

    .label { display: block; font-size: 13px; color: #888; margin-bottom: 8px; font-weight: 500; }

    .input-wrap {
      display: flex; align-items: center;
      background: #f8f9fa; border: 1.5px solid #eee;
      border-radius: 14px; padding: 0 14px; height: 50px;
      transition: all .3s;

      &:focus-within { border-color: #20bf6b; background: #fff; box-shadow: 0 0 0 4px rgba(32,191,107,0.08); }

      .input-prefix { margin-right: 8px; font-size: 16px; }
      .input-box { flex: 1; height: 100%; border: none; font-size: 16px; background: transparent; }
      .input-unit { font-size: 13px; color: #999; margin-left: 8px; }
    }

    .picker-box {
      display: flex; justify-content: space-between; align-items: center;
      background: #f8f9fa; border: 1.5px solid #eee;
      border-radius: 14px; padding: 0 14px; height: 50px;
      font-size: 16px; color: #333;
    }
    .picker-arrow { color: #ccc; font-size: 12px; }
  }

  .submit-btn {
    width: 100%; height: 50px;
    background: linear-gradient(135deg, #20bf6b, #0fb9b1);
    border-radius: 25px; border: none; color: #fff;
    font-size: 16px; font-weight: bold; letter-spacing: 1px;
    box-shadow: 0 4px 15px rgba(32,191,107,0.3);
    transition: all .3s;
    display: flex; align-items: center; justify-content: center;
  }
  .submit-btn:active { transform: scale(.97); }
  .submit-btn[disabled] { opacity: .6; }
}
</style>
