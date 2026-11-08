<!--
组件路径: /components/VoiceInput.vue
组件功能: 语音录入弹窗 
  - H5: 使用 Web Speech API 识别语音
  - 微信小程序: 使用 RecorderManager 录音 + 后端 ASR 识别
  两端共用: NLP解析确认 + 提交入库
依赖: VITE_VOICE_INPUT_ENABLED(env配置开关), VITE_VOICE_TIMEOUT
-->
<template>
  <view class="voice-mask" v-if="visible" @touchmove.prevent>
    <view class="voice-panel" @click.stop>
      <view class="panel-header">
        <text class="panel-title">语音录入</text>
        <text class="panel-close" @click="close">✕</text>
      </view>

      <!-- 步骤1: 录音/输入 -->
      <view class="panel-body" v-if="step === 'record'">
        <!-- 录音按钮区域 -->
        <view
          class="mic-area"
          @tap="toggleRecording"
        >
          <view class="mic-outer" :class="{ recording: isRecording, disabled: !voiceEnabled }">
            <view class="mic-inner">
              <text class="mic-icon">{{ isRecording ? '🔴' : '🎤' }}</text>
            </view>
          </view>
        </view>

        <!-- H5: 语音识别结果预览 -->
        <!-- #ifdef H5 -->
        <text class="mic-hint" v-if="voiceEnabled">{{ isRecording ? '松开停止识别' : '按住说话' }}</text>
        <!-- #endif -->
        <!-- #ifdef MP-WEIXIN -->
        <text class="mic-hint" v-if="voiceEnabled">{{ isRecording ? '松开停止录音' : '按住说话' }}</text>
        <!-- #endif -->
        <text class="mic-hint" v-else>语音功能未启用</text>
        <text class="mic-sub-hint" v-if="isRecording">{{ recordingTime }}s</text>

        <!-- 识别/录音中 加载提示 -->
        <view class="loading-tip" v-if="isProcessing">
          <text class="loading-text">识别中...</text>
        </view>

        <!-- H5: 实时识别结果 -->
        <!-- #ifdef H5 -->
        <view class="recorded-text" v-if="recognizedText">
          <text class="recorded-label">识别结果：</text>
          <text class="recorded-content">{{ recognizedText }}</text>
        </view>
        <!-- #endif -->

        <!-- 分隔线 -->
        <view class="manual-divider">
          <text class="divider-line"></text>
          <text class="divider-text">或手动输入</text>
          <text class="divider-line"></text>
        </view>

        <!-- 手动输入区域 -->
        <view class="manual-input-area">
          <textarea class="manual-textarea" v-model="manualText" placeholder="例如：血糖5.6，血压12080，体重65kg" />
          <button class="manual-btn" @click="parseManualText">解析并提交</button>
        </view>
      </view>

      <!-- 步骤2: 确认解析结果 -->
      <view class="panel-body" v-if="step === 'confirm'">
        <view class="confirm-hint">
          <text>请确认以下数据是否正确：</text>
        </view>

        <view class="record-list">
          <view class="record-card" v-for="(item, idx) in parsedRecords" :key="idx">
            <view class="card-left">
              <view class="type-tag" :class="'type-' + item.type">
                {{ item.type_cn }}
              </view>
            </view>
            <view class="card-center">
              <text class="card-value">{{ item.value }}
                <text class="card-unit">{{ unitMap[item.type] }}</text>
              </text>
              <text class="card-time">{{ item.time_text }}</text>
            </view>
            <view class="card-right">
              <text class="card-edit" @click="editRecord(idx)">编辑</text>
            </view>
          </view>
        </view>

        <view class="empty-tip" v-if="parsedRecords.length === 0">
          <text>未能识别出有效数据，请重试</text>
        </view>
      </view>

      <!-- 编辑弹窗：支持修改数值与时间 -->
      <view class="edit-popup" v-if="showEdit">
        <view class="edit-mask" @click="closeEdit"></view>
        <view class="edit-box">
          <text class="edit-title">编辑{{ parsedRecords[editIndex] ? parsedRecords[editIndex].type_cn : '' }}</text>
          <view class="edit-row">
            <text class="edit-label">数值</text>
            <input
              class="edit-input"
              type="text"
              v-model="editValue"
              :placeholder="'输入' + (parsedRecords[editIndex] ? parsedRecords[editIndex].type_cn : '') + '值'"
            />
            <text class="edit-unit">{{ parsedRecords[editIndex] ? unitMap[parsedRecords[editIndex].type] : '' }}</text>
          </view>
          <view class="edit-row">
            <text class="edit-label">时间</text>
            <picker
              class="edit-picker"
              mode="time"
              :value="editTime"
              @change="onEditTimeChange"
            >
              <view class="edit-picker-text">{{ editTime || '选择时间' }}</view>
            </picker>
          </view>
          <view class="edit-actions">
            <view class="edit-btn cancel" @click="closeEdit">取消</view>
            <view class="edit-btn confirm" @click="saveEdit">保存</view>
          </view>
        </view>
      </view>

      <!-- 底部按钮 -->
      <view class="panel-footer">
        <view class="footer-btn secondary" @click="reRecord" v-if="step === 'confirm'">
          重新录入
        </view>
        <view class="footer-btn primary" @click="submitData" v-if="step === 'confirm' && parsedRecords.length > 0" :class="{ disabled: submitting }">
          {{ submitting ? '提交中...' : '确认提交' }}
        </view>
      </view>
    </view>

    <!-- 提交成功遮罩 -->
    <view class="success-overlay" v-if="showSuccess">
      <view class="success-box">
        <text class="success-icon">✅</text>
        <text class="success-text">录入成功！</text>
        <text class="success-count">共提交 {{ submitCount }} 条记录</text>
      </view>
    </view>
  </view>
</template>

<script>
import { api } from '@/utils/api.js'

export default {
  props: {
    visible: { type: Boolean, default: false }
  },
  emits: ['close', 'submitted'],
  data() {
    return {
      step: 'record',
      isRecording: false,
      recordingTime: 0,
      timer: null,
      // H5: Web Speech API
      recognition: null,
      recognizedText: '',
      // MP-WEIXIN: RecorderManager
      recorderManager: null,
      tempFilePath: '',
      isProcessing: false,
      // 通用
      manualText: '',
      parsedRecords: [],
      submitting: false,
      showSuccess: false,
      submitCount: 0,
      // 编辑弹窗（支持编辑数值 + 时间）
      showEdit: false,
      editIndex: -1,
      editValue: '',
      editTime: '',
      // 防止 H5 上 mouse/touch 重复触发
      _recordingLock: false,
      unitMap: {
        glucose: 'mmol/L',
        blood_pressure: 'mmHg',
        weight: 'kg',
        waist: 'cm'
      }
    }
  },
  computed: {
    voiceEnabled() {
      return import.meta.env.VITE_VOICE_INPUT_ENABLED !== 'false'
    }
  },
  watch: {
    visible(val) {
      if (val) this.reset()
    }
  },
  beforeDestroy() {
    this.stopRecording()
  },
  mounted() {
    // 微信小程序录音初始化在 startMpRecording 中按需进行
  },
  methods: {
    // ========== 初始化 ==========

    reset() {
      // 停止仍在进行的录音
      this.stopRecording()
      this.step = 'record'
      this.isRecording = false
      this.recordingTime = 0
      this.recognizedText = ''
      this.manualText = ''
      this.parsedRecords = []
      this.submitting = false
      this.showSuccess = false
      this.submitCount = 0
      this.isProcessing = false
      this.tempFilePath = ''
      if (this.timer) clearInterval(this.timer)
    },

    close() {
      this.stopRecording()
      this.$emit('close')
    },

// ========== 切换录音状态（单击开始/停止） ==========

	    // #ifdef MP-WEIXIN
	    toggleRecording() {
	      if (!this.voiceEnabled) {
	        return uni.showToast({ title: '语音功能已禁用', icon: 'none' })
	      }

	      if (this.isRecording) {
	        this.stopRecording()
	      } else {
	        this.isRecording = true
	        this.recordingTime = 0
	        this.recognizedText = ''
	        this.timer = setInterval(() => { this.recordingTime++ }, 1000)
	        this.startMpRecording()
	      }
	    },

	    stopRecording() {
	      if (!this.isRecording) return
	      this.isRecording = false
	      if (this.timer) { clearInterval(this.timer); this.timer = null }
	      this.stopMpRecording()
	    },

startMpRecording() {
		      console.log('[VoiceInput] 开始录音 (RecorderManager, mp3)')

		      // 使用 RecorderManager 替代 wx.startRecord（后者产生 silk，百度不支持）
		      this.recorderManager = wx.getRecorderManager()

		      this.recorderManager.onStop((res) => {
		        console.log('[VoiceInput] 录音完成:', res.tempFilePath)
		        this.isRecording = false
		        if (this.timer) { clearInterval(this.timer); this.timer = null }
		        this.tempFilePath = res.tempFilePath
		        if (this.tempFilePath) {
		          this.uploadAndParseAudio()
		        } else {
		          uni.showToast({ title: '录音失败，请重试', icon: 'none' })
		        }
		      })

		      this.recorderManager.onError((err) => {
		        console.error('[VoiceInput] 录音错误:', err)
		        this.isRecording = false
		        if (this.timer) { clearInterval(this.timer); this.timer = null }
		        uni.showToast({ title: '录音失败: ' + (err.errMsg || '未知错误'), icon: 'none' })
		      })

this.recorderManager.start({
        duration: 60000,
        sampleRate: 16000,
        numberOfChannels: 1,
        encodeBitRate: 48000,
        format: 'wav'
      })

		      // 设置超时自动停止
		      const timeout = parseInt(import.meta.env.VITE_VOICE_TIMEOUT || '10')
		      this._recordTimer = setTimeout(() => {
		        console.log('[VoiceInput] 录音超时，自动停止')
		        if (this.recorderManager) {
		          this.recorderManager.stop()
		        }
		      }, timeout * 1000)
		    },

		    stopMpRecording() {
		      console.log('[VoiceInput] 停止录音 (recorderManager.stop)')
		      if (this._recordTimer) {
		        clearTimeout(this._recordTimer)
		        this._recordTimer = null
		      }
		      if (this.recorderManager) {
		        this.recorderManager.stop()
		      }
		    },
	    // #endif

	    // #ifdef H5
	    toggleRecording() {
	      if (!this.voiceEnabled) {
	        return uni.showToast({ title: '语音功能已禁用', icon: 'none' })
	      }

	      if (this.isRecording) {
	        this.stopRecording()
	      } else {
	        this.isRecording = true
	        this.recordingTime = 0
	        this.recognizedText = ''
	        this.timer = setInterval(() => { this.recordingTime++ }, 1000)
	        this.startH5Recording()
	      }
	    },

	    stopRecording() {
	      if (!this.isRecording) return
	      this.isRecording = false
	      if (this.timer) { clearInterval(this.timer); this.timer = null }
	      this.stopH5Recording()
	    },

	    startH5Recording() {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
      if (!SpeechRecognition) {
        uni.showToast({ title: '浏览器不支持语音识别，请使用下方手动输入', icon: 'none' })
        this.isRecording = false
        if (this.timer) { clearInterval(this.timer); this.timer = null }
        return
      }

      console.log('[VoiceInput] 开始创建 SpeechRecognition')
      console.log('[VoiceInput] 浏览器支持:', typeof SpeechRecognition)

      this.recognition = new SpeechRecognition()
      this.recognition.lang = 'zh-CN'
      this.recognition.continuous = true
      this.recognition.interimResults = true

      this.recognition.onstart = () => {
        console.log('[VoiceInput] 语音识别已启动')
      }

      this.recognition.onresult = (e) => {
        console.log('[VoiceInput] onresult 触发, results.length:', e.results.length)
        let text = ''
        for (let i = e.resultIndex; i < e.results.length; i++) {
          const result = e.results[i]
          console.log(`[VoiceInput] result[${i}]:`, result, 'isFinal:', result.isFinal)
          const transcript = result[0].transcript
          // 只取最终结果（非临时结果）
          if (!result.isFinal) continue
          text += transcript
        }
        if (text) {
          console.log('[VoiceInput] 识别到文本:', text)
          this.recognizedText = text
        }
      }

      this.recognition.onerror = (e) => {
        console.warn('[VoiceInput] 识别错误:', e.error, 'detail:', e.detail)
        // 静音/取消/无结果 → 不弹错误，静默停止
        if (e.error === 'no-speech' || e.error === 'aborted') {
          this.isRecording = false
          if (this.timer) { clearInterval(this.timer); this.timer = null }
          // #ifdef H5
          if (typeof document !== 'undefined') {
            document.removeEventListener('mouseup', this._onMouseUp)
          }
          // #endif
          return
        }
        uni.showToast({ title: '语音识别失败: ' + e.error, icon: 'none' })
        this.stopRecording()
      }

      this.recognition.onend = () => {
        console.log('[VoiceInput] 识别已停止')
        // 如果还在录音状态，自动重启（防止浏览器中断）
        if (this.isRecording && this.recognition) {
          console.log('[VoiceInput] 自动重启识别器')
          try { this.recognition.start() } catch (e) {}
        }
      }

      try {
        console.log('[VoiceInput] 调用 start()...')
        this.recognition.start()
        console.log('[VoiceInput] start() 调用完成')
      } catch (e) {
        console.error('[VoiceInput] start() 抛出异常:', e)
        uni.showToast({ title: '语音识别启动失败: ' + e.message, icon: 'none' })
        this.isRecording = false
        if (this.timer) { clearInterval(this.timer); this.timer = null }
        // #ifdef H5
        if (typeof document !== 'undefined') {
          document.removeEventListener('mouseup', this._onMouseUp)
        }
        // #endif
      }
    },
    // #endif

    // ========== H5 停止语音识别 ==========

    // #ifdef H5
    stopH5Recording() {
      if (this.recognition) {
        try { this.recognition.stop() } catch (e) {}
        this.recognition = null
      }
      if (this.recognizedText) {
        this.parseText()
      } else if (this.recordingTime < 1) {
        uni.showToast({ title: '按住时间太短，请长按后说话', icon: 'none' })
      } else {
        uni.showToast({ title: '未检测到语音，请靠近麦克风说话', icon: 'none' })
      }
    },
    // #endif

    // ========== 上传音频并解析 (MP-WEIXIN) ==========

    // #ifdef MP-WEIXIN
    async uploadAndParseAudio() {
      console.log('[VoiceInput] 准备上传音频文件:', this.tempFilePath)
      if (!this.tempFilePath) {
        uni.showToast({ title: '未获取到录音文件', icon: 'none' })
        return
      }

      this.isProcessing = true
      try {
        uni.showLoading({ title: '语音识别中...' })
        const res = await api.voiceRecognize(this.tempFilePath)
        uni.hideLoading()
        this.isProcessing = false
        console.log('[VoiceInput] 识别结果:', res)
        this.parsedRecords = res.records || []
        this.step = 'confirm'
      } catch (e) {
        uni.hideLoading()
        this.isProcessing = false
        console.error('[VoiceInput] 识别失败:', e)
      }
},
    // #endif

    // ========== 解析文本 (H5 + 手动输入通用) ==========

    async parseText() {
      if (!this.recognizedText) {
        uni.showToast({ title: '未能识别到语音', icon: 'none' })
        return
      }
      try {
        uni.showLoading({ title: '解析中...' })
        const res = await api.voiceParse(this.recognizedText)
        uni.hideLoading()
        this.parsedRecords = res.records || []
        this.step = 'confirm'
      } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '解析失败', icon: 'none' })
      }
    },

    reRecord() {
      this.step = 'record'
      this.parsedRecords = []
      this.recognizedText = ''
      this.manualText = ''
      this.tempFilePath = ''
      this.isProcessing = false
    },

    async parseManualText() {
      if (!this.manualText.trim()) {
        return uni.showToast({ title: '请输入内容', icon: 'none' })
      }
      try {
        uni.showLoading({ title: '解析中...' })
        const res = await api.voiceParse(this.manualText.trim())
        uni.hideLoading()
        this.parsedRecords = res.records || []
        this.step = 'confirm'
      } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '解析失败', icon: 'none' })
      }
    },

    // ========== 编辑记录 ==========

    editRecord(idx) {
      const item = this.parsedRecords[idx]
      if (!item) return
      this.editIndex = idx
      this.editValue = item.value
      this.editTime = item.time_text || ''
      this.showEdit = true
    },

    onEditTimeChange(e) {
      this.editTime = e.detail.value
    },

    closeEdit() {
      this.showEdit = false
      this.editIndex = -1
    },

    saveEdit() {
      const idx = this.editIndex
      if (idx < 0) return
      const item = this.parsedRecords[idx]
      if (this.editValue !== '' && this.editValue !== null) {
        item.value = this.editValue
      }
      if (this.editTime) {
        const [h, m] = this.editTime.split(':').map(Number)
        const d = new Date((item.measure_time || Math.floor(Date.now() / 1000)) * 1000)
        d.setHours(h, m, 0, 0)
        item.measure_time = Math.floor(d.getTime() / 1000)
        item.time_text = this.editTime
      }
      this.closeEdit()
    },

    // ========== 提交数据 ==========

    async submitData() {
      if (this.submitting) return
      this.submitting = true
      try {
        uni.showLoading({ title: '提交中...' })
        const res = await api.voiceSubmit(this.parsedRecords)
        uni.hideLoading()
        this.submitCount = (res.saved || []).length
        this.showSuccess = true
        setTimeout(() => {
          this.showSuccess = false
          this.$emit('submitted')
          this.close()
        }, 1500)
      } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '提交失败', icon: 'none' })
      } finally {
        this.submitting = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.voice-mask {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  animation: fadeIn .2s ease;
}
.voice-panel {
  width: 85%;
  max-width: 380px;
  background: #fff;
  border-radius: 24px;
  overflow: hidden;
  animation: slideUp .3s ease;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 20px 0;
}
.panel-title { font-size: 18px; font-weight: bold; color: #333; }
.panel-close { font-size: 20px; color: #999; padding: 10px; }

.panel-body { padding: 30px 20px; min-height: 200px; }

.mic-area { display: flex; justify-content: center; margin-bottom: 20px; }
.mic-outer {
  width: 100px; height: 100px; border-radius: 50%;
  background: linear-gradient(135deg, #e8f8f0, #d4f5e8);
  display: flex; align-items: center; justify-content: center;
  transition: all .3s; cursor: pointer;
}
.mic-outer.recording {
  background: linear-gradient(135deg, #ff6b6b, #ff4757);
  transform: scale(1.05);
  animation: pulse 1.2s infinite;
}
.mic-outer.disabled {
  background: #eee;
  cursor: not-allowed;
}
.mic-inner {
  width: 70px; height: 70px; border-radius: 50%;
  background: #fff; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.mic-icon { font-size: 32px; }

.mic-hint { text-align: center; font-size: 16px; color: #333; font-weight: bold; }
.mic-sub-hint { text-align: center; font-size: 13px; color: #999; margin-top: 5px; }

.recorded-text {
  margin-top: 20px; padding: 15px; background: #f7f8fa; border-radius: 12px;
}
.recorded-label { font-size: 12px; color: #999; display: block; margin-bottom: 5px; }
.recorded-content { font-size: 15px; color: #333; line-height: 1.6; }

.loading-tip { text-align: center; padding: 20px; color: #999; }
.loading-text { font-size: 14px; color: #20bf6b; }

.confirm-hint { text-align: center; font-size: 14px; color: #666; margin-bottom: 20px; }

.record-list { margin-bottom: 15px; }
.record-card {
  display: flex; align-items: center; padding: 15px;
  background: #f7f8fa; border-radius: 14px; margin-bottom: 10px;
}
.card-left { margin-right: 15px; }
.type-tag {
  padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; color: #fff;
}
.type-glucose { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
.type-blood_pressure { background: linear-gradient(135deg, #74b9ff, #0984e3); }
.type-weight { background: linear-gradient(135deg, #a29bfe, #6c5ce7); }
.type-waist { background: linear-gradient(135deg, #fdcb6e, #e17055); }
.card-center { flex: 1; }
.card-value { font-size: 18px; font-weight: bold; color: #333; }
.card-unit { font-size: 12px; font-weight: normal; color: #999; margin-left: 2px; }
.card-time { font-size: 12px; color: #999; margin-top: 2px; display: block; }
.card-right { margin-left: 10px; }
.card-edit { font-size: 13px; color: #20bf6b; padding: 5px; }

.panel-footer {
  display: flex; padding: 0 20px 20px; gap: 10px;
}
.footer-btn {
  flex: 1; height: 48px; border-radius: 24px; display: flex;
  align-items: center; justify-content: center; font-size: 15px; font-weight: bold; cursor: pointer;
}
.footer-btn.primary { background: linear-gradient(135deg, #20bf6b, #0fb9b1); color: #fff; }
.footer-btn.primary.disabled { opacity: 0.6; }
.footer-btn.secondary { background: #f7f8fa; color: #666; }

.manual-divider {
  display: flex; align-items: center; margin: 24px 0 16px;
}
.divider-line { flex: 1; height: 1px; background: #eee; }
.divider-text { margin: 0 12px; font-size: 12px; color: #ccc; }

.manual-input-area {
  .manual-textarea {
    width: 100%; min-height: 80px; border: 1.5px solid #eee;
    border-radius: 14px; padding: 12px; font-size: 14px; color: #333;
    background: #f8f9fa; box-sizing: border-box;
    resize: none;
  }
  .manual-btn {
    width: 100%; height: 44px; margin-top: 12px;
    background: linear-gradient(135deg, #20bf6b, #0fb9b1);
    border-radius: 22px; color: #fff; font-size: 14px; font-weight: bold;
    border: none; display: flex; align-items: center; justify-content: center;
  }
  .manual-btn:active { opacity: 0.8; }
}

.empty-tip { text-align: center; padding: 20px; color: #999; }

.success-overlay {
  position: absolute; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(255,255,255,0.95);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn .3s ease;
}
.success-box { text-align: center; }
.success-icon { font-size: 48px; display: block; margin-bottom: 15px; }
.success-text { font-size: 20px; font-weight: bold; color: #20bf6b; }
.success-count { font-size: 14px; color: #999; margin-top: 8px; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.08); } 100% { transform: scale(1); } }

/* 编辑弹窗 */
.edit-popup { position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 20; }
.edit-mask { position: absolute; inset: 0; background: rgba(0,0,0,0.45); }
.edit-box {
  position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
  width: 80%; background: #fff; border-radius: 14px; padding: 20px 18px;
  box-sizing: border-box; animation: slideUp .25s ease;
}
.edit-title { font-size: 16px; font-weight: bold; color: #333; display: block; margin-bottom: 14px; }
.edit-row { display: flex; align-items: center; margin-bottom: 14px; }
.edit-label { font-size: 14px; color: #666; width: 40px; flex-shrink: 0; }
.edit-input {
  flex: 1; height: 38px; border: 1px solid #e0e0e0; border-radius: 8px;
  padding: 0 10px; font-size: 15px; background: #fafafa;
}
.edit-unit { font-size: 13px; color: #999; margin-left: 8px; white-space: nowrap; }
.edit-picker { flex: 1; }
.edit-picker-text {
  height: 38px; line-height: 38px; border: 1px solid #e0e0e0; border-radius: 8px;
  padding: 0 10px; font-size: 15px; background: #fafafa; color: #333;
}
.edit-actions { display: flex; gap: 12px; margin-top: 6px; }
.edit-btn {
  flex: 1; height: 40px; line-height: 40px; text-align: center; border-radius: 20px; font-size: 15px;
}
.edit-btn.cancel { background: #f0f0f0; color: #666; }
.edit-btn.confirm { background: #20bf6b; color: #fff; }
</style>