<template>
  <view class="page">
    <view class="entry-card">
      <view class="entry-header">
        <text class="entry-icon">🩸</text>
        <text class="entry-title">血糖记录</text>
      </view>
      <view class="entry-body">
        <view class="input-row">
          <input class="input" v-model="form.value" placeholder="血糖值" type="digit" />
          <text class="unit">mmol/L</text>
        </view>
        <view class="status-tags">
          <view class="tag-item" v-for="(label, key) in statusTags" :key="key" :class="{ active: form.measure_status == key }" @click="form.measure_status = Number(key)">
            <text class="tag-icon">{{ tagIcons[key] || '🏷️' }}</text>
            <text class="tag-label">{{ label }}</text>
          </view>
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
            <text class="record-value">{{ item.value }} <text class="record-unit">mmol/L</text></text>
            <text class="record-time">{{ formatTime(item.measure_time) }}</text>
          </view>
          <view class="record-right">
            <text class="tag">{{ item.measure_status_text }}</text>
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
      list: [],
      page: 1,
      limit: 15,
      total: 0,
      has_more: true,
      loading: false,
      form: { value: '', measure_status: 0 },
      statusTags: {},
      tagIcons: { 0: '🌅', 1: '🌄', 2: '🍳', 3: '☀️', 4: '🍱', 5: '🌆', 6: '🍜', 7: '🌙', 8: '🎲' }
    }
  },
  onShow() { this.resetAndLoad(); this.autoSelectStatus(); this.loadStatusTags() },
  onReachBottom() { this.loadMore() },
  methods: {
    autoSelectStatus() {
      const h = new Date().getHours()
      if (h >= 4 && h < 7) this.form.measure_status = 0
      else if (h >= 7 && h < 8) this.form.measure_status = 1
      else if (h >= 9 && h < 11) this.form.measure_status = 2
      else if (h >= 11 && h < 12) this.form.measure_status = 3
      else if (h >= 13 && h < 15) this.form.measure_status = 4
      else if (h >= 15 && h < 18) this.form.measure_status = 5
      else if (h >= 19 && h < 21) this.form.measure_status = 6
      else if (h >= 21 || h < 4) this.form.measure_status = 7
      else this.form.measure_status = 8
    },
    async loadStatusTags() {
      try {
        const res = await api.getGlucoseStatusTags()
        this.statusTags = res || {}
      } catch (e) { console.error(e) }
    },
    async resetAndLoad() {
      this.list = []; this.page = 1; this.has_more = true; this.total = 0
      await this.loadList()
    },
    async loadList() {
      if (this.loading || !this.has_more) return
      this.loading = true
      try {
        const res = await api.getGlucoseList({ page: this.page, limit: this.limit })
        const items = res.list || []
        this.list.push(...items)
        this.total = res.total || 0
        this.has_more = res.has_more !== false
      } catch (e) { console.error(e) }
      this.loading = false
    },
    loadMore() {
      if (!this.has_more || this.loading) return
      this.page++
      this.loadList()
    },
    async doAdd() {
      await api.addGlucose({ value: this.form.value, measure_status: this.form.measure_status, measure_time: Math.floor(Date.now() / 1000) })
      uni.showToast({ title: '保存成功', icon: 'success' })
      this.form = { value: '', measure_status: 0 }
      this.resetAndLoad()
    },
    async doDelete(id) {
      const res = await new Promise(r => {
        uni.showModal({ title: '确认删除', content: '确定要删除这条记录吗？', success: (e) => r(e.confirm) })
      })
      if (!res) return
      await api.deleteGlucose(id)
      this.list = this.list.filter(v => v.id !== id)
      this.total--
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
.entry-card { background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,184,148,0.3); }
.entry-header { display: flex; align-items: center; margin-bottom: 16px; }
.entry-icon { font-size: 24px; margin-right: 8px; }
.entry-title { font-size: 18px; font-weight: bold; color: #fff; }
.entry-body { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 16px; }
.input-row { display: flex; align-items: center; border: 1px solid #eee; border-radius: 10px; padding: 0 12px; margin-bottom: 12px; background: #fafafa; }
.input { flex: 1; height: 44px; font-size: 16px; border: none; outline: none; background: transparent; }
.unit { font-size: 14px; color: #999; min-width: 50px; text-align: right; }
.status-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.tag-item { width: calc(33.33% - 6px); display: flex; flex-direction: column; align-items: center; padding: 10px 2px; border-radius: 12px; background: #f5f5f5; border: 2px solid transparent; transition: all .2s; box-sizing: border-box; }
.tag-item.active { background: #e8f8f0; border-color: #00b894; }
.tag-icon { font-size: 18px; margin-bottom: 4px; }
.tag-label { font-size: 11px; color: #666; white-space: nowrap; }
.tag-item.active .tag-label { color: #00b894; font-weight: bold; }
.btn-save { width: 100%; height: 44px; background: linear-gradient(135deg, #00b894, #00cec9); color: #fff; border-radius: 22px; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center; border: none; }
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
.tag { font-size: 11px; color: #00b894; background: #e8f8f0; padding: 3px 10px; border-radius: 10px; margin-bottom: 4px; }
.btn-del { font-size: 12px; color: #ff4757; padding: 2px 6px; }
.load-more { text-align: center; padding: 20px 0; }
.load-text { font-size: 13px; color: #b2bec3; }
.load-text.done { color: #dfe6e9; }
</style>
