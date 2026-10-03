/**
 * ProofAge Age Verification for PrestaShop
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Starts the ProofAge flow and polls our backend for the outcome.
 * The outcome is never taken from the SDK or postMessage events.
 */
(function () {
  'use strict';

  var config = window.proofageGate;
  if (!config) {
    return;
  }

  var POLL_INTERVAL = 2000;
  var POLL_LIMIT = 30;
  var busy = false;
  var polling = false;
  var pollLimit = 0;

  function each(selector, fn) {
    var nodes = document.querySelectorAll(selector);
    for (var i = 0; i < nodes.length; i++) {
      fn(nodes[i]);
    }
  }

  function setStatus(text) {
    each('[data-proofage-status]', function (node) { node.textContent = text || ''; });
  }

  function setBusy(value) {
    busy = value;
    each('[data-proofage-start]', function (node) { node.disabled = value; });
  }

  function showButton() {
    each('[data-proofage-start]', function (node) { node.hidden = false; });
  }

  function finish(text) {
    polling = false;
    setStatus(text);
    setBusy(false);
    showButton();
  }

  function withParam(url, name, value) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + name + '=' + encodeURIComponent(value);
  }

  function loadSdk() {
    return new Promise(function (resolve, reject) {
      if (window.KycService) {
        resolve(window.KycService);
        return;
      }
      var script = document.createElement('script');
      script.src = config.sdkUrl;
      script.async = true;
      script.onload = function () {
        if (window.KycService) {
          resolve(window.KycService);
        } else {
          reject(new Error('sdk'));
        }
      };
      script.onerror = function () { reject(new Error('sdk')); };
      document.head.appendChild(script);
    });
  }

  function openModal(url) {
    return loadSdk().then(function (sdk) {
      sdk.init({ apiKey: config.publicKey, language: config.language });
      sdk.onComplete(function () { poll(0, POLL_LIMIT); });
      // Closed without finishing: check once (the webhook may already have landed) and give the button back.
      sdk.onClose(function () { poll(0, 1); });
      sdk.onError(function (error) {
        finish(error && error.code === 'INSECURE_CONTEXT' ? config.texts.insecure : config.texts.error);
      });
      sdk.start({ verificationUrl: url });
      setStatus(config.texts.inProgress);
    }, function () {
      // SDK unavailable (blocked script, network): fall back to the hosted page.
      window.location.assign(url);
    });
  }

  function start() {
    if (busy) {
      return;
    }
    setBusy(true);
    setStatus(config.texts.starting);

    var body = new URLSearchParams();
    body.append('token', config.token);
    body.append('back', config.backUrl);

    fetch(config.sessionUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (response) {
      return response.json().then(function (data) { return { status: response.status, data: data }; });
    }).then(function (result) {
      if (result.status === 429) {
        finish(config.texts.rateLimited);
        return;
      }
      if (result.status !== 200 || !result.data.url) {
        finish(config.texts.error);
        return;
      }
      if (result.data.launch_mode === 'redirect') {
        window.location.assign(result.data.url);
        return;
      }
      return openModal(result.data.url);
    }).catch(function () {
      finish(config.texts.error);
    });
  }

  function poll(attempt, limit) {
    if (attempt === 0) {
      if (polling) {
        // A completion arriving during the single post-close check upgrades it to full polling.
        pollLimit = Math.max(pollLimit, limit);
        return;
      }
      polling = true;
      pollLimit = limit;
      setStatus(config.texts.checking);
    }

    fetch(withParam(config.statusUrl, 'back', config.backUrl), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      switch (data.state) {
        case 'approved':
          setStatus(config.texts.approved);
          window.location.assign(data.redirect || config.backUrl);
          return;
        case 'declined':
          finish(config.texts.declined);
          return;
        case 'retry':
          finish(config.texts.retry);
          return;
        case 'reset':
        case 'none':
          finish(config.texts.reset);
          return;
        default:
          if (data.status === 'review') {
            setStatus(config.texts.review);
          }
          if (attempt + 1 >= pollLimit) {
            finish(data.status === 'review' ? config.texts.review : config.texts.stillPending);
            return;
          }
          setTimeout(function () { poll(attempt + 1, pollLimit); }, POLL_INTERVAL);
      }
    }).catch(function () {
      if (attempt + 1 >= pollLimit) {
        finish(config.texts.error);
        return;
      }
      setTimeout(function () { poll(attempt + 1, pollLimit); }, POLL_INTERVAL);
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('[data-proofage-start]') : null;
    if (target) {
      event.preventDefault();
      start();
    }
  });

  // The refusal payload as each theme hands it to 'handleError': the parsed JSON (add-to-cart), a jQuery XHR
  // (classic quickview) or an Error whose message ends with the response body (hummingbird quickview).
  function refusal(resp) {
    if (!resp) {
      return null;
    }
    if (resp.proofage) {
      return resp;
    }
    if (resp.responseJSON) {
      return resp.responseJSON;
    }
    var text = typeof resp.message === 'string' ? resp.message : '';
    var start = text.indexOf('{');
    if (start === -1) {
      return null;
    }
    try {
      return JSON.parse(text.slice(start));
    } catch (e) {
      return null;
    }
  }

  // Add-to-cart or product AJAX (quickview) refused by the server (see Gatekeeper): send the visitor to the gate.
  if (window.prestashop && typeof window.prestashop.on === 'function') {
    window.prestashop.on('handleError', function (event) {
      var data = refusal(event && event.resp);
      if (data && data.proofage && data.proofage.required && data.proofage.gateUrl) {
        window.location.assign(data.proofage.gateUrl);
      }
    });
  }

  if (config.returnMode) {
    poll(0, POLL_LIMIT);
  }
})();
