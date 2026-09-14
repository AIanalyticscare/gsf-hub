(function () {
  function isCompleteStatus(status) {
    status = String(status || "").toLowerCase();
    return status === "completed" || status === "passed";
  }

  function getPlayers() {
    return window.gsfScormLitePlayers || {};
  }

  function getPrimarySlug() {
    var players = getPlayers();
    var keys = Object.keys(players);
    return keys.length ? keys[0] : "";
  }

  function getSettings(slug) {
    var players = getPlayers();
    return players[slug] || players[getPrimarySlug()] || null;
  }

  function attemptIsComplete(settings) {
    var attempt = settings && settings.attempt ? settings.attempt : {};

    return isCompleteStatus(attempt.lesson_status) || isCompleteStatus(attempt.completion_status);
  }

  function sectionSlug(section) {
    return section.getAttribute("data-gsf-lms-feedback-course-slug") || getPrimarySlug();
  }

  function getForm(section) {
    return section.querySelector(".gsf-lms-feedback__form") || section.querySelector("form");
  }

  function getCourseId(section) {
    var input = section.querySelector('input[name="course_id"]');
    return input ? parseInt(input.value, 10) || 0 : 0;
  }

  function setNotice(section, message) {
    var notice = section.querySelector(".gsf-lms-feedback__notice");

    if (notice) {
      notice.textContent = message;
    }
  }

  function setToggleBusy(section, busy) {
    var toggle = section.querySelector(".gsf-lms-feedback__toggle");

    if (!toggle) {
      return;
    }

    toggle.disabled = !!busy;
    toggle.setAttribute("aria-busy", busy ? "true" : "false");
  }

  function setFormEnabled(section, enabled) {
    var form = getForm(section);

    if (!form) {
      return;
    }

    form.hidden = !enabled;
    form.style.display = enabled ? "" : "none";
    form.querySelectorAll("input, textarea, select, button").forEach(function (field) {
      field.disabled = !enabled;
    });
  }

  function setIncomplete(section, message) {
    section.classList.add("gsf-lms-feedback--locked");
    section.dataset.gsfFeedbackReady = "0";
    setNotice(section, message || "Complete the course before submitting feedback.");
    setFormEnabled(section, false);
    setToggleBusy(section, false);
  }

  function setReady(section, message) {
    section.classList.remove("gsf-lms-feedback--locked");
    section.dataset.gsfFeedbackReady = "1";
    setNotice(section, message || "Your course is complete. Submit the feedback survey to unlock your certificate.");
    setFormEnabled(section, true);
    setToggleBusy(section, false);
  }

  function setDone(section, message) {
    section.classList.remove("gsf-lms-feedback--locked");
    section.dataset.gsfFeedbackReady = "1";
    setNotice(section, message || "Feedback survey completed. Your course certificate is now available.");
    setFormEnabled(section, false);
    setToggleBusy(section, false);
  }

  function scrollToCertificateOrReload() {
    var certificate = document.querySelector("#gsf-lms-certificate, .gsf-lms-certificate");

    if (certificate) {
      certificate.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }

    window.location.hash = "gsf-lms-certificate";
    window.location.reload();
  }

  function statusEndpoint(courseId) {
    var config = window.gsfLearnFeedbackGate || {};

    if (!config.restBase || !courseId) {
      return "";
    }

    return String(config.restBase).replace(/\/?$/, "/") + encodeURIComponent(courseId);
  }

  function fetchStatus(section) {
    var courseId = getCourseId(section);
    var endpoint = statusEndpoint(courseId);
    var config = window.gsfLearnFeedbackGate || {};

    if (!endpoint) {
      return Promise.resolve({
        canSubmit: attemptIsComplete(getSettings(sectionSlug(section))),
        hasFeedback: false,
        hasCertificate: false,
        messages: {
          ready: "Your course is complete. Submit the feedback survey to unlock your certificate.",
          incomplete: "Complete the course before submitting feedback.",
          done: "Feedback survey completed. Your course certificate is now available."
        }
      });
    }

    return fetch(endpoint, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        "X-WP-Nonce": config.nonce || ""
      }
    }).then(function (response) {
      if (!response.ok) {
        throw new Error("Status check failed.");
      }

      return response.json();
    });
  }

  function applyStatus(section, status, openWhenReady) {
    var messages = status.messages || {};

    if (status.hasFeedback && status.hasCertificate) {
      setDone(section, messages.done);

      if (openWhenReady) {
        scrollToCertificateOrReload();
      }

      return;
    }

    if (status.canSubmit) {
      setReady(section, messages.ready);
      return;
    }

    setIncomplete(section, messages.incomplete);
  }

  function checkSection(section, openWhenReady) {
    setToggleBusy(section, true);
    setNotice(section, "Checking course completion...");

    return fetchStatus(section)
      .then(function (status) {
        applyStatus(section, status, openWhenReady);
      })
      .catch(function () {
        if (attemptIsComplete(getSettings(sectionSlug(section)))) {
          setReady(section);
        } else {
          setIncomplete(section, "Complete the course before submitting feedback.");
        }
      });
  }

  function initializeSection(section) {
    if (section.textContent.indexOf("Feedback survey completed") !== -1) {
      setDone(section);
      return;
    }

    section.classList.remove("gsf-lms-feedback--locked");
    section.dataset.gsfFeedbackReady = "0";
    setFormEnabled(section, false);
    setNotice(section, "Click the button to check completion and open the feedback survey.");
    setToggleBusy(section, false);
  }

  function completionTextFound(text) {
    text = String(text || "").toLowerCase();

    return text.indexOf("congratulations on completing") !== -1 ||
      text.indexOf("receive credit for completion") !== -1 ||
      text.indexOf("course complete") !== -1 ||
      text.indexOf("course completed") !== -1;
  }

  function collectFrameText(doc, depth) {
    var text = "";
    var frames;

    if (!doc || !doc.body || depth > 3) {
      return "";
    }

    text = doc.body.innerText || doc.body.textContent || "";
    frames = doc.querySelectorAll("iframe, frame");

    frames.forEach(function (frame) {
      try {
        text += "\n" + collectFrameText(frame.contentDocument, depth + 1);
      } catch (error) {
        text += "";
      }
    });

    return text;
  }

  function postCompletedAttempt(settings) {
    var attempt = settings.attempt || {};
    var data = Object.assign({}, attempt.scorm_data || {});

    data["cmi.core.lesson_status"] = "completed";
    data["cmi.completion_status"] = "completed";
    data["cmi.core.score.raw"] = data["cmi.core.score.raw"] || attempt.score_raw || "100";

    settings.attempt = Object.assign({}, attempt, {
      lesson_status: "completed",
      completion_status: "completed",
      score_raw: data["cmi.core.score.raw"],
      scorm_data: data
    });

    if (!settings.restUrl || !settings.nonce) {
      return Promise.resolve(false);
    }

    return fetch(settings.restUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": settings.nonce
      },
      body: JSON.stringify({ data: data })
    }).then(function () {
      return true;
    }).catch(function () {
      return false;
    });
  }

  function markComplete(slug) {
    var settings = getSettings(slug);

    if (!settings || attemptIsComplete(settings)) {
      document.querySelectorAll(".gsf-lms-feedback").forEach(function (section) {
        checkSection(section, false);
      });
      return;
    }

    postCompletedAttempt(settings).then(function () {
      document.querySelectorAll(".gsf-lms-feedback").forEach(function (section) {
        checkSection(section, false);
      });
    });
  }

  function watchPlayer(container) {
    var frame = container.querySelector("iframe");
    var slug = container.getAttribute("data-gsf-scorm-slug") || getPrimarySlug();
    var checks = 0;
    var completed = false;
    var timer;

    if (!frame) {
      return;
    }

    function check() {
      var text = "";

      if (completed || checks > 900) {
        window.clearInterval(timer);
        return;
      }

      checks += 1;

      try {
        text = collectFrameText(frame.contentDocument, 0);
      } catch (error) {
        text = "";
      }

      if (completionTextFound(text)) {
        completed = true;
        window.clearInterval(timer);
        markComplete(slug);
      }
    }

    frame.addEventListener("load", function () {
      checks = 0;
      window.setTimeout(check, 800);
    });

    timer = window.setInterval(check, 2000);
    window.setTimeout(check, 800);
  }

  function bindFeedbackUi() {
    document.querySelectorAll(".gsf-lms-feedback").forEach(initializeSection);
    document.querySelectorAll(".gsf-scorm-lite[data-gsf-scorm-slug]").forEach(watchPlayer);
  }

  document.addEventListener("click", function (event) {
    var toggle = event.target.closest(".gsf-lms-feedback__toggle");

    if (!toggle) {
      return;
    }

    var section = toggle.closest(".gsf-lms-feedback");

    if (!section) {
      return;
    }

    event.preventDefault();
    checkSection(section, true);
  });

  document.addEventListener("submit", function (event) {
    var section = event.target.closest(".gsf-lms-feedback");

    if (section && section.dataset.gsfFeedbackReady !== "1") {
      event.preventDefault();
      checkSection(section, false);
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindFeedbackUi);
  } else {
    bindFeedbackUi();
  }
})();
