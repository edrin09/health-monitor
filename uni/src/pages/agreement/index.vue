<template>
  <view class="agreement-page">
    <view class="loading" v-if="loading">加载中...</view>
    <view class="content" v-else>
      <rich-text :nodes="content"></rich-text>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  data() {
    return {
      loading: true,
      content: ''
    }
  },
  onLoad(options) {
    this.type = options.type || 'user_agreement'
    this.loadContent()
  },
  methods: {
    async loadContent() {
      try {
        const res = await api.getAgreement()
        this.content = res[this.type] || '暂无内容'
      } catch (e) {
        this.content = '获取内容失败'
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style scoped>
.agreement-page {
  padding: 30rpx;
  min-height: 100vh;
  background: #fff;
}
.loading {
  text-align: center;
  padding: 100rpx 0;
  color: #999;
  font-size: 28rpx;
}
.content {
  font-size: 28rpx;
  line-height: 1.8;
  color: #333;
}
</style>