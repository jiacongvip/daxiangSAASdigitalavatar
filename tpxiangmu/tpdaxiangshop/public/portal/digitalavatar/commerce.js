(function () {
  function api(action) {
    var qs = new URLSearchParams({
      s: 'portalapi/digitalavatar/' + action,
      platform: 'h5',
      apifrom: 'react',
    })
    return fetch('/website.php?' + qs.toString(), {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({}),
    }).then(function (res) {
      return res.json()
    }).then(function (json) {
      if (Number(json.status) !== 1) {
        throw new Error(json.msg || '请求失败')
      }
      return json.data
    })
  }

  function applyOem(oem) {
    if (!oem) return
    var title = String(oem.title || '').trim()
    if (title) document.title = title
    var color = String(oem.color || '').trim()
    if (color) document.documentElement.style.setProperty('--da-oem-color', color)
    var bg = String(oem.login_bg || '').trim()
    if (bg) document.documentElement.style.setProperty('--da-oem-login-bg', 'url("' + bg.replace(/"/g, '') + '")')
  }

  function injectStyle() {
    if (document.getElementById('da-commerce-style')) return
    var style = document.createElement('style')
    style.id = 'da-commerce-style'
    style.textContent = ':root{--da-oem-color:#7c5cff}'
    document.head.appendChild(style)
  }

  function boot() {
    injectStyle()
    api('bootstrap').then(function (data) {
      var oem = (data && data.app && data.app.oem) || (data && data.login_config && data.login_config.oem) || {}
      applyOem(oem)
    }).catch(function () {
      api('getLoginConfig').then(function (data) {
        applyOem((data && data.oem) || {})
      }).catch(function () {})
    })
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }
})()
