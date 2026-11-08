/**
 * API 接口封装
 * 统一处理: 请求签名、Token注入、错误处理(含601自动跳登录)
 * 基础地址: import.meta.env.VITE_API_BASE_URL
 */
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8787/api'

let isRedirecting = false

const request = (options) => {
  return new Promise((resolve, reject) => {
    const token = uni.getStorageSync('token') || ''

    const data = {
      ...options.data,
      authenticate: {
        token: token
      }
    }

    uni.request({
      url: BASE_URL + options.url,
      method: options.method || 'POST',
      data: data,
      header: {
        'Content-Type': 'application/json',
        ...options.header
      },
      success: (res) => {
        console.log('[API] 原始响应:', options.url, res.statusCode, JSON.stringify(res.data).slice(0,500))
        if (res.statusCode === 200) {
          const responseData = res.data
          if (responseData.code === 200) {
            console.log('[API] 解析成功 data:', JSON.stringify(responseData.data).slice(0,300))
            resolve(responseData.data)
          } else if (responseData.code === 601) {
            console.log('[API] token过期:', options.url)
            // Token过期
            uni.removeStorageSync('token')
            uni.removeStorageSync('userInfo')
            if (!isRedirecting) {
              isRedirecting = true
              uni.showToast({
                title: '登录已过期，请重新登录',
                icon: 'none'
              })
              setTimeout(() => {
                uni.reLaunch({
                  url: '/pages/login/index'
                })
                setTimeout(() => {
                  isRedirecting = false
                }, 2000)
              }, 1500)
            }
            reject(responseData)
          } else {
            uni.showToast({
              title: responseData.msg || responseData.meg || '请求失败',
              icon: 'none'
            })
            reject(responseData)
          }
        } else {
          uni.showToast({
            title: '网络请求失败',
            icon: 'none'
          })
          reject(res)
        }
      },
      fail: (err) => {
        uni.showToast({
          title: '网络连接失败',
          icon: 'none'
        })
        reject(err)
      }
    })
  })
}

export const api = {
  // ===== 微信小程序登录 =====
  // POST /applet/login/doLogin
  // 入参: loginCode(微信code), userInfo.avatar, userInfo.nickname
  // 出参: user_info { customer_id, nickname, avatar, phone_number, expansion.token }
  login(loginCode, userInfo = {}) {
    return request({
      url: '/applet/login/doLogin',
      data: {
        parameter: {
          login_code: loginCode,
          avatar: userInfo.avatar || '',
          nickname: userInfo.nickname || ''
        }
      }
    })
  },

  // ===== H5手机号密码登录 =====
  // POST /h5/login/doLogin
  // 入参: phone(手机号), password
  // 出参: customer_id, phone_number, nickname, avatar, token
  h5Login(phone, password) {
    return request({
      url: '/h5/login/doLogin',
      data: {
        parameter: {
          phone_number: phone,
          password: password
        }
      }
    })
  },

  // ===== 获取用户信息 =====
  // GET /business/customer/getInfo
  // 出参: customer_id, nickname, avatar, phone_number, has_superior, superior_id
  getUserInfo() {
    return request({
      url: '/business/customer/getInfo',
      data: { parameter: {} }
    })
  },

  // ===== 更新用户信息 =====
  // POST /business/customer/updateInfo
  // 入参: nickname(可选), avatar(可选), phone_number(可选), address(可选)
  updateUserInfo(data) {
    return request({
      url: '/business/customer/updateInfo',
      data: { parameter: data }
    })
  },

  // ===== 获取首页仪表盘 =====
  // GET /business/customer/dashboard
  // 出参: bmr, latest_glucose, latest_blood_pressure, latest_weight, latest_waist, records[]
  getDashboard() {
    return request({
      url: '/business/customer/dashboard',
      data: { parameter: {} }
    })
  },

  // ===== 血糖相关 =====

  // GET /business/glucose/list
  // 入参: start_date, end_date, is_page, paging
  getGlucoseList(data = {}) {
    return request({
      url: '/business/glucose/list',
      data: { parameter: data }
    })
  },

  // POST /business/glucose/add
  // 入参: value(float), measure_status(0~4), measure_time, remark
  addGlucose(data) {
    return request({
      url: '/business/glucose/add',
      data: { parameter: data }
    })
  },

  // POST /business/glucose/delete
  // 入参: id(int)
  deleteGlucose(id) {
    return request({
      url: '/business/glucose/delete',
      data: { parameter: { id } }
    })
  },

  // GET /business/glucose/statusTags
  // 出参: { 0: '空腹', 1: '早餐前', ... }
  getGlucoseStatusTags() {
    return request({
      url: '/business/glucose/statusTags',
      data: { parameter: {} }
    })
  },

  // ===== 血压相关 =====

  // GET /business/blood-pressure/list
  getBloodPressureList(data = {}) {
    return request({
      url: '/business/blood-pressure/list',
      data: { parameter: data }
    })
  },

  // POST /business/blood-pressure/add
  // 入参: systolic(int), diastolic(int), pulse(可选), measure_time, remark
  addBloodPressure(data) {
    return request({
      url: '/business/blood-pressure/add',
      data: { parameter: data }
    })
  },

  // POST /business/blood-pressure/delete
  // 入参: id(int)
  deleteBloodPressure(id) {
    return request({
      url: '/business/blood-pressure/delete',
      data: { parameter: { id } }
    })
  },

  // ===== 体重相关 =====

  // GET /business/weight/list
  getWeightList(data = {}) {
    return request({
      url: '/business/weight/list',
      data: { parameter: data }
    })
  },

  // POST /business/weight/add
  // 入参: weight(float), measure_time, remark
  addWeight(data) {
    return request({
      url: '/business/weight/add',
      data: { parameter: data }
    })
  },

  // POST /business/weight/delete
  // 入参: id(int)
  deleteWeight(id) {
    return request({
      url: '/business/weight/delete',
      data: { parameter: { id } }
    })
  },

  // ===== 腰围相关 =====

  // GET /business/waist/list
  getWaistList(data = {}) {
    return request({
      url: '/business/waist/list',
      data: { parameter: data }
    })
  },

  // POST /business/waist/add
  // 入参: value(float), measure_time, remark
  addWaist(data) {
    return request({
      url: '/business/waist/add',
      data: { parameter: data }
    })
  },

  // POST /business/waist/delete
  // 入参: id(int)
  deleteWaist(id) {
    return request({
      url: '/business/waist/delete',
      data: { parameter: { id } }
    })
  },

  // ===== 个人档案 =====

  // GET /business/customer/profile
  // 出参: profile { customer_id, height, gender, birth_date }
  getProfile() {
    return request({
      url: '/business/customer/profile',
      data: { parameter: {} }
    })
  },

  // POST /business/customer/updateProfile
  // 入参: nickname, height, gender(0女/1男), birth_date(YYYY-MM-DD)
  updateProfile(data) {
    return request({
      url: '/business/customer/updateProfile',
      data: { parameter: data }
    })
  },

  // ===== 统计数据 =====

  // GET /business/customer/stats
  // 入参: type(glucose|blood_pressure|weight|waist), period(天数)
  // 出参: stats, list[], chart[]
  getStats(data = {}) {
    return request({
      url: '/business/customer/stats',
      data: { parameter: data }
    })
  },

  // ===== 语音录入 =====

  // POST /business/voice/parse
  // 入参: text(中文自然语言)
  // 出参: records[] 解析后的结构数据
  voiceParse(text) {
    return request({
      url: '/business/voice/parse',
      data: { parameter: { text } }
    })
  },

  // POST /business/voice/submit
  // 入参: records[] 确认后的记录数组
  // 出参: saved[] 已保存的记录
  voiceSubmit(records) {
    return request({
      url: '/business/voice/submit',
      data: { parameter: { records } }
    })
  },

// POST /business/voice/recognize
      // 上传音频文件 → 后端 ASR 识别 + NLP 解析
      // 入参: tempFilePath(录音临时文件路径)
      // 出参: records[] 解析后的结构数据, original(原始文本)
      voiceRecognize(tempFilePath) {
        const token = uni.getStorageSync('token') || ''
        return new Promise((resolve, reject) => {
          uni.uploadFile({
            url: BASE_URL + '/business/voice/recognize',
            filePath: tempFilePath,
            name: 'audio',
            formData: {
              token: token,
              format: 'wav'
            },
            success: (res) => {
          try {
            const data = JSON.parse(res.data)
            if (data.code === 200) {
              resolve(data.data)
            } else if (data.code === 601) {
              // Token 过期
              uni.removeStorageSync('token')
              uni.removeStorageSync('userInfo')
              uni.showToast({ title: '登录已过期，请重新登录', icon: 'none' })
              setTimeout(() => {
                uni.reLaunch({ url: '/pages/login/index' })
              }, 1500)
              reject(data)
            } else {
              uni.showToast({
                title: data.msg || data.meg || '语音识别失败',
                icon: 'none'
              })
              reject(data)
            }
          } catch (e) {
            uni.showToast({ title: '语音识别响应解析失败', icon: 'none' })
            reject(e)
          }
        },
        fail: (err) => {
          uni.showToast({ title: '语音上传失败', icon: 'none' })
          reject(err)
        }
      })
    })
  },

  // ===== 微信小程序获取手机号 =====
  // POST /applet/applet/getUserPhone
  getUserPhone(phoneCode) {
    return request({
      url: '/applet/applet/getUserPhone',
      data: {
        parameter: {
          phone_code: phoneCode,
          app_id: ''
        }
      }
    })
  },

  // ===== 获取协议内容（无需登录） =====
  // GET /public/config/getAgreement
  // 出参: user_agreement, privacy_policy
  getAgreement() {
    return request({
      url: '/public/config/getAgreement',
      data: { parameter: {} }
    })
  }
}

export default api
