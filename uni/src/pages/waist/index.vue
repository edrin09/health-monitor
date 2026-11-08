<template>
  <view class="page">
    <view class="entry-card">
      <view class="entry-header">
        <text class="entry-icon">📏</text>
        <text class="entry-title">腰围记录</text>
      </view>
      <view class="entry-body">
        <view class="input-row">
          <input class="input" v-model="form.value" placeholder="腰围" type="digit" />
          <text class="unit">cm</text>
        </view>
        <button class="btn-save" @click="doAdd">保存</button>
      </view>
    </view>

    <view class="section-hd">
      <text class="section-title">历史记录</text>
      <text class="section-count" v-if="total">{{ total }} 条</text>
    </view>

    <view class="list">
      <view class="record" v-for="(item, idx) in list" :key="item.id || idx">
        <view class="record-main">
          <view class="record-left">
            <text class="record-value">{{ item.value }} <text class="record-unit">cm</text></text>
            <text class="record-time">{{ formatTime(item.measure_time) }}</text>
          </view>
          <view class="record-right">
            <text class="btn-del" @click="doDelete(item.id)">删除</text>
          </view>
        </view>
      </view>
    </view>

    <view class="load-more" v-if="loading">
      <text class="load-text">加载中...</text>
    </view>
    <view class="load-more" v-else-if="!has_more && list.length > 0">
      <text class="load-text done">— 没有更多了 —</text>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'
export default {
  data() {
    return {
      list: [], page: 1, limit: 15, total: 0, has_more: true, loading: false,
      form: { value: '' }
    }
  },
  onShow() { this.resetAndLoad() },
  onReachBottom() { this.loadMore() },
  methods: {
    async resetAndLoad() {
      this.list = []; this.page = 1; this.has_more = true; this.total = 0
      await this.loadList()
    },
    async loadList() {
      if (this.loading || !this.has_more) return
      this.loading = true
      try {
        const res = await api.getWaistList({ page: this.page, limit: this.limit })
        this.list.push(...(res.list || []))
        this.total = res.total || 0
        this.has_more = res.has_more !== false
      } catch (e) { console.error(e) }
      this.loading = false
    },
    loadMore() {
      if (!this.has_more || this.loading) return
      this.page++; this.loadList()
    },
    async doAdd() {
      await api.addWaist({ value: this.form.value, measure_time: Math.floor(Date.now() / 1000) })
      uni.showToast({ title: '保存成功', icon: 'success' })
      this.form = { value: '' }
      this.resetAndLoad()
    },
    async doDelete(id) {
      const res = await new Promise(r => {
        uni.showModal({ title: '确认删除', content: '确定要删除这条记录吗？', success: (e) => r(e.confirm) })
      })
      if (!res) return
      await api.deleteWaist(id)
      this.list = this.list.filter(v => v.id !== id); this.total--
    },
    formatTime(t) {
      if (!t) return ''
      const d = new Date(t * 1000)
      const pad = n => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    }
  }
}
</script>

<style scoped>
.page { padding: 16px; background: #f5f6fa; min-height: 100vh; padding-bottom: 40px; }
.entry-card { background: linear-gradient(135deg, #74b9ff, #0984e3); border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(9,132,227,0.3); }
.entry-header { display: flex; align-items: center; margin-bottom: 16px; }
.entry-icon { font-size: 24px; margin-right: 8px; }
.entry-title { font-size: 18px; font-weight: bold; color: #fff; }
.entry-body { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 16px; }
.input-row { display: flex; align-items: center; border: 1px solid #eee; border-radius: 10px; padding: 0 12px; margin-bottom: 12px; background: #fafafa; }
.input { flex: 1; height: 44px; font-size: 16px; border: none; outline: none; background: transparent; }
.unit { font-size: 14px; color: #999; min-width: 50px; text-align: right; }
.btn-save { width: 100%; height: 44px; background: linear-gradient(135deg, #74b9ff, #0984e3); color: #fff; border-radius: 22px; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center; border: none; }
.section-hd { display: flex; align-items: baseline; margin-bottom: 12px; padding: 0 4px; }
.section-title { font-size: 16px; font-weight: bold; color: #1a1a2e; }
.section-count { font-size: 12px; color: #999; margin-left: 8px; }
.record { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.record-main { display: flex; justify-content: space-between; align-items: center; }
.record-left { flex: 1; }
.record-value { font-size: 18px; font-weight: bold; color: #2d3436; }
.record-unit { font-size: 12px; font-weight: normal; color: #999; }
.record-time { font-size: 12px; color: #b2bec3; margin-top: 4px; display: block; }
.record-right { display: flex; flex-direction: column; align-items: flex-end; }
.btn-del { font-size: 12px; color: #ff4757; padding: 2px 6px; }
.load-more { text-align: center; padding: 20px 0; }
.load-text { font-size: 13px; color: #b2bec3; }
.load-text.done { color: #dfe6e9; }
</style>
