(function () {
  function parseAttempt(attempt, settings) {
    var data = {};

    if (attempt && attempt.scorm_data && typeof attempt.scorm_data === "object") {
      data = Object.assign({}, attempt.scorm_data);
    }

    data["cmi.core.student_id"] = data["cmi.core.student_id"] || settings.studentId || "";
    data["cmi.core.student_name"] = data["cmi.core.student_name"] || settings.name || "";
    data["cmi.core.lesson_status"] = data["cmi.core.lesson_status"] || attempt.lesson_status || "not attempted";
    data["cmi.core.lesson_location"] = data["cmi.core.lesson_location"] || attempt.lesson_location || "";
    data["cmi.suspend_data"] = data["cmi.suspend_data"] || attempt.suspend_data || "";
    data["cmi.core.score.raw"] = data["cmi.core.score.raw"] || attempt.score_raw || "";
    data["cmi.core.score.min"] = data["cmi.core.score.min"] || attempt.score_min || "";
    data["cmi.core.score.max"] = data["cmi.core.score.max"] || attempt.score_max || "";
    data["cmi.core.total_time"] = data["cmi.core.total_time"] || attempt.total_time || "0000:00:00.00";
    data["cmi.core.credit"] = data["cmi.core.credit"] || "credit";
    data["cmi.core.lesson_mode"] = data["cmi.core.lesson_mode"] || "normal";
    data["cmi.launch_data"] = data["cmi.launch_data"] || "";
    data["cmi.comments"] = data["cmi.comments"] || "";
    data["cmi.comments_from_lms"] = data["cmi.comments_from_lms"] || "";
    data["cmi.student_data.mastery_score"] = data["cmi.student_data.mastery_score"] || "";
    data["cmi.student_data.max_time_allowed"] = data["cmi.student_data.max_time_allowed"] || "";
    data["cmi.student_data.time_limit_action"] = data["cmi.student_data.time_limit_action"] || "";

    if (data["cmi.core.lesson_status"] === "not attempted") {
      data["cmi.core.entry"] = "ab-initio";
    } else {
      data["cmi.core.entry"] = "resume";
    }

    data["cmi.core.exit"] = "";

    return data;
  }

  function createApi(settings) {
    var initialized = false;
    var lastError = "0";
    var attempt = settings.attempt || {};
    var data = parseAttempt(attempt, settings);
    var interactions = {};
    var objectives = {};

    function setError(code) {
      lastError = String(code || "0");
    }

    function getCollectionValue(collection, key) {
      if (key.slice(-7) === "._count") {
        return String(Object.keys(collection).length);
      }

      return data[key] || "";
    }

    function isCompleteResponse(response) {
      var attempt = response && response.attempt ? response.attempt : {};
      var status = String(attempt.lesson_status || "").toLowerCase();
      var completionStatus = String(attempt.completion_status || "").toLowerCase();
      var score = parseFloat(attempt.score_raw || "");

      return status === "completed" || status === "passed" || completionStatus === "completed" || completionStatus === "passed" || score >= 100;
    }

    function isCompleteData() {
      var status = String(data["cmi.core.lesson_status"] || "").toLowerCase();
      var completionStatus = String(data["cmi.completion_status"] || "").toLowerCase();
      var score = parseFloat(data["cmi.core.score.raw"] || "");

      return status === "completed" || status === "passed" || completionStatus === "completed" || completionStatus === "passed" || score >= 100;
    }

    function announceCompletion(response) {
      if (!isCompleteResponse(response)) {
        return;
      }

      window.dispatchEvent(new CustomEvent("gsf-scorm-lite:completed", {
        detail: {
          slug: settings.slug,
          attempt: response.attempt || {}
        }
      }));
    }

    function markComplete() {
      data["cmi.core.lesson_status"] = "completed";
      data["cmi.completion_status"] = "completed";
      data["cmi.core.score.raw"] = data["cmi.core.score.raw"] || "100";

      announceCompletion({
        attempt: {
          lesson_status: data["cmi.core.lesson_status"],
          completion_status: data["cmi.completion_status"],
          score_raw: data["cmi.core.score.raw"]
        }
      });

      return save(false);
    }

    function save(sync) {
      var payload = JSON.stringify({ data: data });

      if (sync && navigator.sendBeacon) {
        var blob = new Blob([payload], { type: "application/json" });
        if (isCompleteData()) {
          announceCompletion({
            attempt: {
              lesson_status: data["cmi.core.lesson_status"] || "",
              completion_status: data["cmi.completion_status"] || "",
              score_raw: data["cmi.core.score.raw"] || ""
            }
          });
        }
        return navigator.sendBeacon(settings.restUrl + "?_wpnonce=" + encodeURIComponent(settings.nonce), blob);
      }

      return fetch(settings.restUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": settings.nonce
        },
        body: payload
      }).then(function (response) {
        return response.json().catch(function () {
          return {};
        });
      }).then(function (response) {
        announceCompletion(response);
        return true;
      }).catch(function () {
        return false;
      });
    }

    function normalizeStatus(value) {
      var allowed = ["passed", "completed", "failed", "incomplete", "browsed", "not attempted"];
      value = String(value || "").toLowerCase();
      return allowed.indexOf(value) >= 0 ? value : "incomplete";
    }

    var api = {
      LMSInitialize: function () {
        initialized = true;
        setError("0");

        if (data["cmi.core.lesson_status"] === "not attempted") {
          data["cmi.core.lesson_status"] = "incomplete";
        }

        return "true";
      },

      LMSFinish: function () {
        initialized = false;
        setError("0");
        save(true);
        return "true";
      },

      LMSGetValue: function (key) {
        if (!initialized) {
          setError("301");
          return "";
        }

        setError("0");

        if (key.indexOf("cmi.interactions.") === 0) {
          return getCollectionValue(interactions, key);
        }

        if (key.indexOf("cmi.objectives.") === 0) {
          return getCollectionValue(objectives, key);
        }

        if (Object.prototype.hasOwnProperty.call(data, key)) {
          return data[key];
        }

        return "";
      },

      LMSSetValue: function (key, value) {
        if (!initialized) {
          setError("301");
          return "false";
        }

        setError("0");

        value = String(value == null ? "" : value);

        if (key === "cmi.core.lesson_status") {
          value = normalizeStatus(value);
        }

        if (key.indexOf("cmi.interactions.") === 0) {
          interactions[key] = value;
        }

        if (key.indexOf("cmi.objectives.") === 0) {
          objectives[key] = value;
        }

        data[key] = value;
        return "true";
      },

      LMSCommit: function () {
        if (!initialized) {
          setError("301");
          return "false";
        }

        setError("0");
        save(false);
        return "true";
      },

      LMSGetLastError: function () {
        return lastError;
      },

      LMSGetErrorString: function (code) {
        var errors = {
          "0": "No error",
          "101": "General exception",
          "201": "Invalid argument error",
          "301": "Not initialized",
          "401": "Not implemented error"
        };

        return errors[String(code)] || "Unknown error";
      },

      LMSGetDiagnostic: function (code) {
        return this.LMSGetErrorString(code || lastError);
      },

      GSFMarkComplete: markComplete
    };

    window.addEventListener("beforeunload", function () {
      save(true);
    });

    return api;
  }

  function boot() {
    var players = window.gsfScormLitePlayers || {};
    var containers = document.querySelectorAll("[data-gsf-scorm-slug]");

    containers.forEach(function (container) {
      var slug = container.getAttribute("data-gsf-scorm-slug");
      var settings = players[slug];

      if (!settings) {
        return;
      }

      window.API = createApi(settings);

      var frame = container.querySelector("iframe[data-src]");

      if (frame && frame.getAttribute("src") === "about:blank") {
        frame.setAttribute("src", frame.getAttribute("data-src"));
      }

      watchFrameForVisibleCompletion(container, frame);
    });
  }

  function textLooksComplete(text) {
    text = String(text || "").toLowerCase();

    return text.indexOf("congratulations on completing") !== -1 ||
      text.indexOf("congratulations on completing the") !== -1 ||
      text.indexOf("course complete") !== -1 ||
      text.indexOf("course completed") !== -1 ||
      text.indexOf("receive credit for completion") !== -1;
  }

  function markActiveCourseComplete(slug) {
    if (window.API && typeof window.API.GSFMarkComplete === "function") {
      window.API.GSFMarkComplete();
    }

    window.dispatchEvent(new CustomEvent("gsf-scorm-lite:completed", {
      detail: {
        slug: slug,
        attempt: {
          lesson_status: "completed",
          completion_status: "completed",
          score_raw: "100"
        }
      }
    }));
  }

  function watchFrameForVisibleCompletion(container, frame) {
    if (!container || !frame) {
      return;
    }

    var slug = container.getAttribute("data-gsf-scorm-slug") || "";
    var checks = 0;
    var maxChecks = 900;
    var intervalId;
    var completed = false;

    function collectFrameText(doc, depth) {
      var text = "";
      var childFrames;

      if (!doc || !doc.body || depth > 3) {
        return "";
      }

      text = doc.body.innerText || doc.body.textContent || "";
      childFrames = doc.querySelectorAll("iframe, frame");

      childFrames.forEach(function (childFrame) {
        try {
          text += "\n" + collectFrameText(childFrame.contentDocument, depth + 1);
        } catch (error) {
          text += "";
        }
      });

      return text;
    }

    function check() {
      var text = "";

      if (completed || checks >= maxChecks) {
        window.clearInterval(intervalId);
        return;
      }

      checks += 1;

      try {
        text = collectFrameText(frame.contentDocument, 0);
      } catch (error) {
        text = "";
      }

      if (textLooksComplete(text)) {
        completed = true;
        window.clearInterval(intervalId);
        markActiveCourseComplete(slug);
      }
    }

    frame.addEventListener("load", function () {
      checks = 0;
      window.setTimeout(check, 800);
    });

    intervalId = window.setInterval(check, 2000);
    window.setTimeout(check, 800);
  }

  function unlockFeedback(section) {
    var button = section.querySelector(".gsf-lms-feedback__toggle");
    var notice = section.querySelector(".gsf-lms-feedback__notice");

    section.classList.remove("gsf-lms-feedback--locked");

    if (button) {
      button.disabled = false;
    }

    if (notice) {
      notice.textContent = "Your course is complete. Submit the feedback survey to unlock your certificate.";
    }
  }

  function showFeedbackForm(section) {
    var form = section.querySelector(".gsf-lms-feedback__form");

    if (!form || section.classList.contains("gsf-lms-feedback--locked")) {
      return;
    }

    form.style.display = "";
    form.querySelectorAll("input, textarea, select, button").forEach(function (field) {
      field.disabled = false;
    });
  }

  window.addEventListener("gsf-scorm-lite:completed", function (event) {
    var slug = event.detail && event.detail.slug ? event.detail.slug : "";
    var safeSlug = window.CSS && CSS.escape ? CSS.escape(slug) : String(slug).replace(/"/g, '\\"');
    var selector = '[data-gsf-lms-feedback-course-slug="' + safeSlug + '"]';
    var section = document.querySelector(selector);

    if (section) {
      unlockFeedback(section);
    }
  });

  document.addEventListener("click", function (event) {
    var button = event.target.closest(".gsf-lms-feedback__toggle");

    if (!button || button.disabled) {
      return;
    }

    var section = button.closest(".gsf-lms-feedback");

    if (section) {
      showFeedbackForm(section);
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
