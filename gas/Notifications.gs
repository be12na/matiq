var NOTIF_RETRY_MAX = 3;
var NOTIF_RETRY_DELAY_MS = 1200;

function getNotificationConfig_() {
  return {
    mailketing_url: String(getScriptConfig_('MAILKETING_API_URL', '') || '').trim(),
    mailketing_key: String(getScriptConfig_('MAILKETING_API_KEY', '') || '').trim(),
    mailketing_sender: String(getScriptConfig_('MAILKETING_SENDER', 'no-reply@matiq.local') || 'no-reply@matiq.local').trim(),
    mailketing_timeout_ms: Number(getScriptConfig_('MAILKETING_TIMEOUT_MS', '15000')) || 15000,
    starsender_url: String(getScriptConfig_('STARSENDER_API_URL', '') || '').trim(),
    starsender_key: String(getScriptConfig_('STARSENDER_API_KEY', '') || '').trim(),
    starsender_device_id: String(getScriptConfig_('STARSENDER_DEVICE_ID', '') || '').trim(),
    starsender_timeout_ms: Number(getScriptConfig_('STARSENDER_TIMEOUT_MS', '15000')) || 15000,
    retry_max: Number(getScriptConfig_('NOTIFICATION_RETRY_MAX', String(NOTIF_RETRY_MAX))) || NOTIF_RETRY_MAX,
    retry_delay_ms: Number(getScriptConfig_('NOTIFICATION_RETRY_DELAY_MS', String(NOTIF_RETRY_DELAY_MS))) || NOTIF_RETRY_DELAY_MS,
    app_url: String(getScriptConfig_('APP_PUBLIC_URL', '') || '').trim()
  };
}

function normalizeWhatsappNumber_(raw) {
  var src = String(raw || '').trim();
  if (!src) return { ok: false, error: 'Nomor WhatsApp wajib diisi' };
  var digits = src.replace(/[^0-9]/g, '');
  if (!digits) return { ok: false, error: 'Nomor WhatsApp tidak valid' };
  if (digits.indexOf('00') === 0) digits = digits.slice(2);
  if (digits.indexOf('0') === 0) digits = '62' + digits.slice(1);
  if (digits.indexOf('62') !== 0) return { ok: false, error: 'Nomor WhatsApp harus format Indonesia (62xx...)' };
  if (digits.length < 10 || digits.length > 15) return { ok: false, error: 'Panjang nomor WhatsApp tidak valid' };
  return { ok: true, normalized: digits };
}

function appendNotificationLog_(entry) {
  appendRows_('notification_logs', [{
    id: String(entry.id || ('notif_' + Utilities.getUuid())),
    event_type: String(entry.event_type || ''),
    channel: String(entry.channel || ''),
    recipient: String(entry.recipient || ''),
    status: String(entry.status || 'failed'),
    attempt: Number(entry.attempt || 1),
    provider: String(entry.provider || ''),
    http_status: String(entry.http_status || ''),
    error_message: String(entry.error_message || ''),
    response_excerpt: String(entry.response_excerpt || '').slice(0, 500),
    queue_id: String(entry.queue_id || ''),
    user_id: String(entry.user_id || ''),
    created_at: String(entry.created_at || new Date().toISOString()),
    updated_at: String(entry.updated_at || new Date().toISOString())
  }]);
}

function callWithRetry_(fn, retryMax, retryDelayMs) {
  var max = Math.max(1, Number(retryMax) || 1);
  var delay = Math.max(300, Number(retryDelayMs) || 1000);
  var last = null;
  for (var i = 1; i <= max; i++) {
    last = fn(i);
    if (last && last.ok) return last;
    if (i < max && last && last.retryable !== false) {
      Utilities.sleep(delay * i);
    }
  }
  return last || { ok: false, error: 'Gagal menjalankan retry' };
}

function sendMailketingEmail_(to, subject, htmlBody, textBody, eventType, userId) {
  var cfg = getNotificationConfig_();
  if (!cfg.mailketing_url || !cfg.mailketing_key) {
    return { ok: false, error: 'Mailketing belum dikonfigurasi', retryable: false };
  }
  var run = callWithRetry_(function (attempt) {
    try {
      var payload = {
        from: cfg.mailketing_sender,
        to: String(to || '').trim(),
        subject: String(subject || ''),
        html: String(htmlBody || ''),
        text: String(textBody || ''),
        event_type: eventType || ''
      };
      var res = UrlFetchApp.fetch(cfg.mailketing_url, {
        method: 'post',
        contentType: 'application/json',
        payload: JSON.stringify(payload),
        headers: {
          Authorization: 'Bearer ' + cfg.mailketing_key,
          'X-API-KEY': cfg.mailketing_key
        },
        muteHttpExceptions: true,
        followRedirects: true,
        validateHttpsCertificates: true,
        timeout: cfg.mailketing_timeout_ms
      });
      var code = Number(res.getResponseCode() || 0);
      var body = String(res.getContentText() || '');
      var ok = code >= 200 && code < 300;
      appendNotificationLog_({
        event_type: eventType,
        channel: 'email',
        recipient: to,
        status: ok ? 'sent' : 'failed',
        attempt: attempt,
        provider: 'mailketing',
        http_status: String(code),
        error_message: ok ? '' : ('Mailketing HTTP ' + code),
        response_excerpt: body,
        user_id: userId || ''
      });
      return {
        ok: ok,
        http_status: code,
        error: ok ? '' : ('Mailketing HTTP ' + code),
        retryable: !ok && (code === 408 || code === 429 || code >= 500),
        body: body
      };
    } catch (err) {
      var msg = err && err.message ? err.message : String(err);
      appendNotificationLog_({
        event_type: eventType,
        channel: 'email',
        recipient: to,
        status: 'failed',
        attempt: attempt,
        provider: 'mailketing',
        error_message: msg,
        user_id: userId || ''
      });
      return { ok: false, error: msg, retryable: true };
    }
  }, cfg.retry_max, cfg.retry_delay_ms);
  return run;
}

function enqueueWhatsappMessage_(item) {
  var now = new Date().toISOString();
  var queueId = 'waq_' + Utilities.getUuid();
  appendRows_('whatsapp_queue', [{
    queue_id: queueId,
    user_id: String(item.user_id || ''),
    email: String(item.email || '').toLowerCase().trim(),
    phone_number: String(item.phone_number || '').trim(),
    message_type: String(item.message_type || 'account_confirmation'),
    message_payload: JSON.stringify(item.message_payload || {}),
    status: 'pending',
    attempt_count: 0,
    max_attempts: Number(item.max_attempts || getNotificationConfig_().retry_max),
    next_retry_at: now,
    last_error: '',
    provider_message_id: '',
    created_at: now,
    updated_at: now
  }]);
  return queueId;
}

function sendWhatsappViaStarsender_(phoneNumber, messageText, messageType, userId, queueId, attempt) {
  var cfg = getNotificationConfig_();
  if (!cfg.starsender_url || !cfg.starsender_key) {
    return { ok: false, error: 'Starsender belum dikonfigurasi', retryable: false };
  }
  try {
    var payload = {
      to: String(phoneNumber || ''),
      message: String(messageText || ''),
      device_id: cfg.starsender_device_id || ''
    };
    var res = UrlFetchApp.fetch(cfg.starsender_url, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify(payload),
      headers: {
        Authorization: 'Bearer ' + cfg.starsender_key,
        'X-API-KEY': cfg.starsender_key
      },
      muteHttpExceptions: true,
      followRedirects: true,
      validateHttpsCertificates: true,
      timeout: cfg.starsender_timeout_ms
    });
    var code = Number(res.getResponseCode() || 0);
    var body = String(res.getContentText() || '');
    var ok = code >= 200 && code < 300;
    appendNotificationLog_({
      event_type: messageType,
      channel: 'whatsapp',
      recipient: phoneNumber,
      status: ok ? 'sent' : 'failed',
      attempt: Number(attempt || 1),
      provider: 'starsender',
      http_status: String(code),
      error_message: ok ? '' : ('Starsender HTTP ' + code),
      response_excerpt: body,
      queue_id: queueId || '',
      user_id: userId || ''
    });
    return {
      ok: ok,
      error: ok ? '' : ('Starsender HTTP ' + code),
      retryable: !ok && (code === 408 || code === 429 || code >= 500),
      provider_message_id: ok ? String(code) : '',
      body: body,
      http_status: code
    };
  } catch (err) {
    var msg = err && err.message ? err.message : String(err);
    appendNotificationLog_({
      event_type: messageType,
      channel: 'whatsapp',
      recipient: phoneNumber,
      status: 'failed',
      attempt: Number(attempt || 1),
      provider: 'starsender',
      error_message: msg,
      queue_id: queueId || '',
      user_id: userId || ''
    });
    return { ok: false, error: msg, retryable: true };
  }
}

function renderRegistrationWhatsappMessage_(user) {
  var name = String((user && user.name) || 'User');
  return 'Halo ' + name + ', akun MATIQ Anda berhasil dibuat dan sudah aktif. Silakan login untuk mulai menggunakan dashboard.';
}

function renderRegistrationEmail_(user, token) {
  var cfg = getNotificationConfig_();
  var base = cfg.app_url || '';
  var verifyLink = base ? (base + (base.indexOf('?') >= 0 ? '&' : '?') + 'verify_token=' + encodeURIComponent(token || '')) : '';
  var name = String((user && user.name) || 'User');
  var html = '<p>Halo ' + name + ',</p>' +
    '<p>Pendaftaran akun MATIQ berhasil.</p>' +
    (verifyLink ? ('<p>Verifikasi sesi: <a href="' + verifyLink + '">Klik di sini</a></p>') : '<p>Akun Anda sudah aktif dan siap digunakan.</p>') +
    '<p>Salam,<br>MATIQ</p>';
  var text = 'Halo ' + name + ', pendaftaran MATIQ berhasil. ' + (verifyLink ? ('Verifikasi sesi: ' + verifyLink) : 'Akun Anda sudah aktif.');
  return { subject: 'Verifikasi Pendaftaran MATIQ', html: html, text: text };
}

function renderLoginEmail_(user) {
  var name = String((user && user.name) || 'User');
  var now = new Date().toISOString();
  return {
    subject: 'Notifikasi Login MATIQ',
    html: '<p>Halo ' + name + ',</p><p>Terdeteksi login ke akun MATIQ pada ' + now + ' (UTC).</p><p>Jika ini bukan Anda, segera ubah password.</p>',
    text: 'Halo ' + name + ', terdeteksi login ke akun MATIQ pada ' + now + ' (UTC). Jika ini bukan Anda, segera ubah password.'
  };
}

function processWhatsappQueue_(maxItems) {
  ensureDbReady();
  var nowIso = new Date().toISOString();
  var rows = getSheetRows_('whatsapp_queue');
  var limit = Math.max(1, Math.min(50, Number(maxItems) || 10));
  var pending = rows.filter(function (r) {
    var status = String(r.status || '').toLowerCase();
    var next = String(r.next_retry_at || '');
    var attempts = Number(r.attempt_count || 0);
    var maxAttempts = Number(r.max_attempts || getNotificationConfig_().retry_max);
    return (status === 'pending' || status === 'retry') && attempts < maxAttempts && (!next || next <= nowIso);
  }).slice(0, limit);

  var result = { processed: 0, sent: 0, failed: 0, retried: 0 };
  pending.forEach(function (item) {
    var attempt = Number(item.attempt_count || 0) + 1;
    var payload = {};
    try { payload = JSON.parse(String(item.message_payload || '{}')); } catch (e) { payload = {}; }
    var messageText = String(payload.message || 'Konfirmasi akun MATIQ');
    var sendRes = sendWhatsappViaStarsender_(item.phone_number, messageText, item.message_type || 'account_confirmation', item.user_id, item.queue_id, attempt);
    var updates = {
      attempt_count: attempt,
      updated_at: new Date().toISOString(),
      last_error: sendRes.ok ? '' : String(sendRes.error || 'unknown error')
    };
    if (sendRes.ok) {
      updates.status = 'sent';
      updates.provider_message_id = sendRes.provider_message_id || '';
      result.sent++;
    } else {
      var maxAttempts = Number(item.max_attempts || getNotificationConfig_().retry_max);
      if (attempt >= maxAttempts || sendRes.retryable === false) {
        updates.status = 'failed';
        result.failed++;
      } else {
        updates.status = 'retry';
        updates.next_retry_at = new Date(Date.now() + (attempt * 60 * 1000)).toISOString();
        result.retried++;
      }
    }
    updateSheetRowByKey_('whatsapp_queue', 'queue_id', item.queue_id, updates);
    result.processed++;
  });

  return result;
}

function getNotificationDashboardStats_(limit) {
  ensureDbReady();
  var logs = getSheetRows_('notification_logs');
  var queue = getSheetRows_('whatsapp_queue');
  var recent = logs
    .sort(function (a, b) { return String(b.created_at || '').localeCompare(String(a.created_at || '')); })
    .slice(0, Math.max(1, Math.min(100, Number(limit) || 20)));

  var summary = {
    total_logs: logs.length,
    email_sent: 0,
    email_failed: 0,
    whatsapp_sent: 0,
    whatsapp_failed: 0,
    whatsapp_pending: 0,
    whatsapp_retry: 0,
    queue_total: queue.length
  };

  logs.forEach(function (l) {
    var ch = String(l.channel || '').toLowerCase();
    var st = String(l.status || '').toLowerCase();
    if (ch === 'email') {
      if (st === 'sent') summary.email_sent++;
      else summary.email_failed++;
    }
    if (ch === 'whatsapp') {
      if (st === 'sent') summary.whatsapp_sent++;
      else summary.whatsapp_failed++;
    }
  });

  queue.forEach(function (q) {
    var st = String(q.status || '').toLowerCase();
    if (st === 'pending') summary.whatsapp_pending++;
    if (st === 'retry') summary.whatsapp_retry++;
  });

  return {
    summary: summary,
    recent_logs: recent
  };
}

function sendRegistrationNotifications_(user, token, whatsappNumber) {
  var out = { email_ok: false, whatsapp_queued: false, whatsapp_processed: false, list_injection_ok: false, errors: [] };
  try {
    var emailTpl = renderRegistrationEmail_(user, token);
    var emailRes = sendMailketingEmail_(user.email, emailTpl.subject, emailTpl.html, emailTpl.text, 'register_verification', user.id);
    out.email_ok = !!(emailRes && emailRes.ok);
    if (!out.email_ok) out.errors.push('Email: ' + String((emailRes && emailRes.error) || 'gagal kirim'));
  } catch (err) {
    out.errors.push('Email exception: ' + (err && err.message ? err.message : String(err)));
  }
  
  // Inject user to Mailketing list if list ID is provided
  if (user && user.mailketing_list_id) {
    try {
      var listRes = injectUserToMailketingList_(user, user.mailketing_list_id);
      out.list_injection_ok = !!(listRes && listRes.ok);
      if (!out.list_injection_ok) out.errors.push('List Injection: ' + String((listRes && listRes.error) || 'gagal inject'));
    } catch (err) {
      out.errors.push('List Injection exception: ' + (err && err.message ? err.message : String(err)));
    }
  }
  
  if (whatsappNumber) {
    var queueId = enqueueWhatsappMessage_({
      user_id: user.id,
      email: user.email,
      phone_number: whatsappNumber,
      message_type: 'account_confirmation',
      message_payload: { message: renderRegistrationWhatsappMessage_(user) }
    });
    out.whatsapp_queued = !!queueId;
    var run = processWhatsappQueue_(1);
    out.whatsapp_processed = run.processed > 0;
  }
  return out;
}

function sendLoginNotification_(user) {
  var emailTpl = renderLoginEmail_(user);
  var emailRes = sendMailketingEmail_(user.email, emailTpl.subject, emailTpl.html, emailTpl.text, 'login_notification', user.id);
  return { email_ok: !!(emailRes && emailRes.ok), error: emailRes && emailRes.ok ? '' : String((emailRes && emailRes.error) || '') };
}


// ─────────────────────────────────────────────────────────────────────────────
// MAILKETING LIST INJECTION
// ─────────────────────────────────────────────────────────────────────────────

function injectUserToMailketingList_(user, listId) {
  if (!user || !user.id || !user.email || !listId) {
    return { ok: false, error: 'User dan List ID wajib diisi', retryable: false };
  }
  
  var cfg = getNotificationConfig_();
  if (!cfg.mailketing_key) {
    return { ok: false, error: 'Mailketing API key belum dikonfigurasi', retryable: false };
  }
  
  // Mailketing list injection endpoint
  var listUrl = 'https://api.mailketing.co.id/api/v1/subscribe';
  
  try {
    var payload = {
      api_token: cfg.mailketing_key,
      email: String(user.email || '').toLowerCase().trim(),
      list_id: String(listId || '').trim(),
      first_name: String((user.name || '').split(' ')[0] || ''),
      last_name: String((user.name || '').split(' ').slice(1).join(' ') || '')
    };
    
    var options = {
      method: 'post',
      payload: payload,
      muteHttpExceptions: true,
      followRedirects: true,
      validateHttpsCertificates: true,
      timeout: cfg.mailketing_timeout_ms || 15000
    };
    
    var res = UrlFetchApp.fetch(listUrl, options);
    var code = Number(res.getResponseCode() || 0);
    var body = String(res.getContentText() || '');
    var ok = code >= 200 && code < 300;
    
    appendNotificationLog_({
      event_type: 'list_injection',
      channel: 'email',
      recipient: user.email,
      status: ok ? 'sent' : 'failed',
      attempt: 1,
      provider: 'mailketing',
      http_status: String(code),
      error_message: ok ? '' : ('Mailketing List Injection HTTP ' + code),
      response_excerpt: body,
      user_id: user.id
    });
    
    return {
      ok: ok,
      http_status: code,
      error: ok ? '' : ('Mailketing HTTP ' + code),
      retryable: !ok && (code === 408 || code === 429 || code >= 500),
      body: body,
      message: ok ? 'User berhasil ditambahkan ke list' : 'Gagal menambahkan user ke list'
    };
  } catch (err) {
    var msg = err && err.message ? err.message : String(err);
    appendNotificationLog_({
      event_type: 'list_injection',
      channel: 'email',
      recipient: user.email,
      status: 'failed',
      attempt: 1,
      provider: 'mailketing',
      error_message: msg,
      user_id: user.id
    });
    return { ok: false, error: msg, retryable: true };
  }
}

function apiGetNotificationStatus_(payload) {
  assertAdminRole_(payload);
  return { ok: true, data: getNotificationDashboardStats_(50) };
}

function apiProcessWhatsappQueue_(payload) {
  assertAdminRole_(payload);
  var maxItems = Number((payload && payload.max_items) || 10);
  return { ok: true, data: processWhatsappQueue_(maxItems) };
}

function runNotificationSelfTest_() {
  var phoneBad = normalizeWhatsappNumber_('0812-ABCD');
  var phoneGood = normalizeWhatsappNumber_('+62 812 3456 7890');
  var retryProbeCount = 0;
  var retryProbe = callWithRetry_(function (attempt) {
    retryProbeCount++;
    if (attempt < 2) return { ok: false, retryable: true, error: 'forced failure' };
    return { ok: true };
  }, 3, 300);
  return {
    ok: true,
    tests: {
      invalid_phone_rejected: !phoneBad.ok,
      valid_phone_accepted: phoneGood.ok,
      retry_mechanism_works: !!retryProbe.ok && retryProbeCount === 2
    }
  };
}
