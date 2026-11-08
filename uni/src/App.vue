<script>
/**
 * App.vue - 应用入口
 */
export default {
  onLaunch: function () {
    console.log('App Launch')
    // 检查录音权限
    this.checkRecordPermission()
  },
  onShow: function () {
    console.log('App Show')
  },
  onHide: function () {
    console.log('App Hide')
  },
  methods: {
    checkRecordPermission() {
      uni.getSetting({
        success: (res) => {
          console.log('[App] 权限设置:', res.authSetting)
          if (!res.authSetting['scope.record']) {
            console.log('[App] 录音权限未授予，请求授权')
            uni.authorize({
              scope: 'scope.record',
              success: () => {
                console.log('[App] 录音权限授权成功')
              },
              fail: (err) => {
                console.warn('[App] 录音权限授权失败:', err)
                // authorize 静默失败是正常的，用户可以在设置中手动开启
              }
            })
          } else {
            console.log('[App] 录音权限已授权')
          }
        }
      })
    }
  }
}
</script>

<style lang="scss">
page {
  background-color: #f5f6fa;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', 'Noto Sans SC', sans-serif;
  color: #333;
}

::-webkit-scrollbar {
  display: none;
  width: 0 !important;
  height: 0 !important;
  -webkit-appearance: none;
  background: transparent;
}

.container {
  padding-bottom: 20px;
}

.card {
  background: #fff;
  border-radius: 20px;
  padding: 20px;
  margin: 12px 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.section-title {
  font-size: 17px;
  font-weight: bold;
  color: #1a1a2e;
  padding: 16px 20px 8px;
}

.empty-tip {
  text-align: center;
  padding: 50px 0;
  color: #bbb;
  font-size: 14px;
}

.custom-nav {
  background: linear-gradient(135deg, #20bf6b, #0fb9b1);
  padding-top: var(--status-bar-height);
  padding-bottom: 14px;
  border-radius: 0 0 24rpx 24rpx;
}

.nav-content {
  padding: 12px 20px 0;
}

.nav-title {
  font-size: 20px;
  font-weight: bold;
  color: #fff;
}

.user-avatar image {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 2px solid #e8f8f0;
  background: #eee;
}

.btn-primary {
  background: linear-gradient(135deg, #20bf6b, #0fb9b1) !important;
  color: #fff !important;
  border: none !important;
}

.btn-primary::after {
  border: none !important;
}
</style>