(function () {
  const config = window.gsfHubInstantFilters;

  if (!config || !config.ajaxUrl || !config.nonce || !config.action) {
    return;
  }

  const debounceTimers = new WeakMap();
  const controllers = new WeakMap();

  const getFormQuery = (form) => {
    const query = {};
    const data = new FormData(form);

    data.forEach((value, key) => {
      if (key.startsWith("_")) {
        return;
      }

      if (value === "") {
        return;
      }

      query[key] = value;
    });

    return query;
  };

  const updateUrl = (form, query) => {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    const url = new URL(window.location.href);
    const formKeys = Array.from(new FormData(form).keys()).filter(
      (key) => !key.startsWith("_")
    );

    formKeys.forEach((key) => url.searchParams.delete(key));

    Object.entries(query).forEach(([key, value]) => {
      if (value !== "") {
        url.searchParams.set(key, value);
      }
    });

    const targetId = form.dataset.gsfFilterTarget;
    if (targetId) {
      url.hash = targetId;
    }

    window.history.replaceState({}, "", url);
  };

  const replaceSection = (target, html) => {
    const template = document.createElement("template");
    const targetId = window.CSS && CSS.escape ? CSS.escape(target.id) : target.id.replace(/"/g, '\\"');

    template.innerHTML = html.trim();
    const next = template.content.querySelector(`#${targetId}`);

    if (!next) {
      return false;
    }

    target.replaceWith(next);
    return true;
  };

  const setLoading = (target, isLoading) => {
    target.classList.toggle("is-filtering", isLoading);
    target.setAttribute("aria-busy", isLoading ? "true" : "false");
  };

  const submitInstantFilter = async (form, options = {}) => {
    const filterType = form.dataset.gsfInstantFilter;
    const targetId = form.dataset.gsfFilterTarget;
    const target = targetId ? document.getElementById(targetId) : form.closest("section");

    if (!filterType || !target) {
      form.submit();
      return;
    }

    const query = options.query || getFormQuery(form);
    const previousController = controllers.get(form);

    if (previousController) {
      previousController.abort();
    }

    const controller = new AbortController();
    controllers.set(form, controller);
    setLoading(target, true);

    const body = new FormData();
    body.append("action", config.action);
    body.append("nonce", config.nonce);
    body.append("filter_type", filterType);
    body.append("page_id", form.dataset.gsfPageId || "");

    Object.entries(query).forEach(([key, value]) => {
      body.append(`query[${key}]`, value);
    });

    try {
      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body,
        signal: controller.signal,
      });
      const payload = await response.json();

      if (!response.ok || !payload.success || !payload.data || !payload.data.html) {
        throw new Error("Invalid filter response");
      }

      if (replaceSection(target, payload.data.html)) {
        updateUrl(form, query);
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        form.submit();
      }
    } finally {
      if (document.body.contains(target)) {
        setLoading(target, false);
      }
    }
  };

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("[data-gsf-instant-filter]");

    if (!form) {
      return;
    }

    event.preventDefault();
    submitInstantFilter(form);
  });

  document.addEventListener("input", (event) => {
    const field = event.target;

    if (!field.matches('[data-gsf-instant-filter] input, [data-gsf-instant-filter] textarea')) {
      return;
    }

    const form = field.closest("[data-gsf-instant-filter]");
    clearTimeout(debounceTimers.get(form));
    debounceTimers.set(
      form,
      setTimeout(() => {
        submitInstantFilter(form);
      }, 320)
    );
  });

  document.addEventListener("change", (event) => {
    const field = event.target;

    if (!field.matches("[data-gsf-instant-filter] select")) {
      return;
    }

    const form = field.closest("[data-gsf-instant-filter]");
    submitInstantFilter(form);
  });

  document.addEventListener("click", (event) => {
    const reset = event.target.closest(
      ".gsf-resource-library__filter-actions a, .directory-filter-plugin__reset, .directory-filters .button--ghost"
    );

    if (!reset) {
      return;
    }

    const section = reset.closest(".gsf-resource-library, #expert-directory");
    const form = section ? section.querySelector("[data-gsf-instant-filter]") : null;

    if (!form) {
      return;
    }

    event.preventDefault();
    form.reset();
    submitInstantFilter(form, { query: {} });
  });
})();
