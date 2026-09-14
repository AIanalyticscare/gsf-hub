(function () {
  "use strict";

  const config = window.gsfResourceAssistant || {};

  const categories = [
    { key: "learn", label: "Learn", short: "L", matches: (type) => type.startsWith("learn") },
    { key: "data", label: "Data Centre", short: "D", matches: (type) => type.startsWith("data centre") || type === "data story" },
    { key: "resources", label: "Resources", short: "R", matches: (type) => type.startsWith("resource") || type === "document" || type === "toolkit" || type === "template" },
    { key: "cases", label: "Case Studies", short: "C", matches: (type) => type.startsWith("case study") },
    { key: "community", label: "Community", short: "F", matches: (type) => type.startsWith("forum") },
    { key: "experts", label: "Experts", short: "E", matches: (type) => type.startsWith("expert") },
    { key: "events", label: "Events", short: "V", matches: (type) => type.includes("webinar") || type.includes("event") },
    { key: "opportunities", label: "Opportunities", short: "O", matches: (type) => /grant|fund|loan|programme|program|training/.test(type) },
  ];

  function categoryFor(resourceType) {
    const normalized = String(resourceType || "").toLowerCase();
    return categories.find((category) => category.matches(normalized)) || {
      key: "other",
      label: resourceType || "Hub content",
      short: "G",
    };
  }

  function addList(parent, title, items, className) {
    if (!Array.isArray(items) || !items.length) return;
    const section = document.createElement("section");
    if (className) section.className = className;
    const heading = document.createElement("h4");
    heading.textContent = title;
    section.appendChild(heading);
    const list = document.createElement("ul");
    items.forEach((item) => {
      const line = document.createElement("li");
      line.textContent = item;
      list.appendChild(line);
    });
    section.appendChild(list);
    parent.appendChild(section);
  }

  function sourceLabel(source) {
    let label = source.document || "Source document";
    if (source.page) label += `, page ${source.page}`;
    else if (source.sheet && source.row) label += `, ${source.sheet} row ${source.row}`;
    else if (source.row) label += `, row ${source.row}`;
    else if (source.paragraph) label += `, paragraph ${source.paragraph}`;
    return label;
  }

  function addSources(card, sources) {
    if (!Array.isArray(sources) || !sources.length) return;
    const section = document.createElement("section");
    section.className = "gsf-resource-assistant__sources";
    const heading = document.createElement("h4");
    heading.textContent = "Verify this recommendation";
    section.appendChild(heading);

    sources.forEach((source) => {
      if (source.url && /^https?:\/\//i.test(source.url)) {
        const link = document.createElement("a");
        link.className = "gsf-resource-assistant__source-link";
        link.href = source.url;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = "Open in GSF Hub ↗";
        section.appendChild(link);
      }
      const citation = document.createElement("details");
      const summary = document.createElement("summary");
      summary.textContent = `Supporting evidence · ${sourceLabel(source)}`;
      citation.appendChild(summary);
      if (source.quote) {
        const quote = document.createElement("blockquote");
        quote.textContent = source.quote;
        citation.appendChild(quote);
      }
      section.appendChild(citation);
    });
    card.appendChild(section);
  }

  function recommendationCard(item, index) {
    const category = categoryFor(item.resource_type);
    const card = document.createElement("article");
    card.className = `gsf-resource-assistant__card gsf-resource-assistant__card--${category.key}`;
    if (index === 0) card.classList.add("gsf-resource-assistant__card--featured");

    const cardTop = document.createElement("div");
    cardTop.className = "gsf-resource-assistant__card-top";
    const categoryBadge = document.createElement("div");
    categoryBadge.className = "gsf-resource-assistant__category";
    const categoryIcon = document.createElement("span");
    categoryIcon.className = "gsf-resource-assistant__category-icon";
    categoryIcon.setAttribute("aria-hidden", "true");
    categoryIcon.textContent = category.short;
    const categoryLabel = document.createElement("span");
    categoryLabel.textContent = category.label;
    categoryBadge.append(categoryIcon, categoryLabel);

    const path = document.createElement("span");
    path.className = "gsf-resource-assistant__path";
    path.textContent = index === 0 ? "Best starting point" : `Path ${String(index + 1).padStart(2, "0")}`;
    cardTop.append(categoryBadge, path);
    card.appendChild(cardTop);

    const title = document.createElement("h3");
    title.textContent = item.resource;
    card.appendChild(title);

    if (item.summary) {
      const summary = document.createElement("p");
      summary.className = "gsf-resource-assistant__summary";
      summary.textContent = item.summary;
      card.appendChild(summary);
    }

    const scoreValue = Math.max(0, Math.min(100, Math.round(Number(item.fit_score || 0) * 100)));
    const score = document.createElement("div");
    score.className = "gsf-resource-assistant__score";
    const scoreLabel = document.createElement("span");
    scoreLabel.textContent = `Match strength ${scoreValue}%`;
    const scoreTrack = document.createElement("span");
    scoreTrack.className = "gsf-resource-assistant__score-track";
    scoreTrack.setAttribute("role", "progressbar");
    scoreTrack.setAttribute("aria-label", `Match strength for ${item.resource}`);
    scoreTrack.setAttribute("aria-valuemin", "0");
    scoreTrack.setAttribute("aria-valuemax", "100");
    scoreTrack.setAttribute("aria-valuenow", String(scoreValue));
    const scoreBar = document.createElement("span");
    scoreBar.style.width = `${scoreValue}%`;
    scoreTrack.appendChild(scoreBar);
    score.append(scoreLabel, scoreTrack);
    card.appendChild(score);

    addList(card, "Why it may help", item.why_it_fits);
    addList(card, "Requirements or possible barriers", item.possible_barriers, "gsf-resource-assistant__caution");

    const applicationResource = /grant|fund|loan|programme|program|opportunity|training/i.test(item.resource_type || "");
    const factEntries = [
      ["Financial support", item.financial_support],
      ["Deadline", item.application_deadline],
      ["Contact", item.contact_information],
    ].filter(([, value]) => applicationResource || (value && value !== "Not stated in the source record"));
    if (factEntries.length) {
      const facts = document.createElement("dl");
      factEntries.forEach(([term, value]) => {
        const dt = document.createElement("dt");
        dt.textContent = term;
        const dd = document.createElement("dd");
        dd.textContent = value || "Not stated in the source record";
        facts.append(dt, dd);
      });
      card.appendChild(facts);
    }

    addList(card, "Suggested next step", item.next_steps);
    addList(card, "What to verify", item.uncertainties, "gsf-resource-assistant__uncertainty");
    addSources(card, item.sources);
    return card;
  }

  function mapHeader(recommendations) {
    const header = document.createElement("header");
    header.className = "gsf-resource-assistant__map-header";
    const kicker = document.createElement("p");
    kicker.className = "gsf-resource-assistant__kicker";
    kicker.textContent = "Your resource map";
    const title = document.createElement("h2");
    title.textContent = "Recommended paths across the GSF Hub";
    const description = document.createElement("p");
    description.textContent = "Start with the strongest match, then explore related learning, evidence, tools, people, and community spaces.";
    header.append(kicker, title, description);

    const counts = new Map();
    recommendations.forEach((item) => {
      const category = categoryFor(item.resource_type);
      const current = counts.get(category.key) || { category, count: 0 };
      current.count += 1;
      counts.set(category.key, current);
    });
    const legend = document.createElement("div");
    legend.className = "gsf-resource-assistant__map-legend";
    counts.forEach(({ category, count }) => {
      const chip = document.createElement("span");
      chip.className = `gsf-resource-assistant__map-chip gsf-resource-assistant__map-chip--${category.key}`;
      chip.textContent = category.label;
      chip.setAttribute("aria-label", `${category.label}: ${count} recommended ${count === 1 ? "path" : "paths"}`);
      legend.appendChild(chip);
    });
    header.appendChild(legend);
    return header;
  }

  function renderResults(container, payload) {
    container.replaceChildren();
    const recommendations = Array.isArray(payload.recommendations) ? payload.recommendations : [];
    if (!recommendations.length) {
      const empty = document.createElement("div");
      empty.className = "gsf-resource-assistant__empty";
      const heading = document.createElement("h3");
      heading.textContent = "No supported match yet";
      const message = document.createElement("p");
      message.textContent = "Try describing your role, the topic you are working on, or the kind of help you want.";
      empty.append(heading, message);
      container.appendChild(empty);
    } else {
      container.appendChild(mapHeader(recommendations));
      const grid = document.createElement("div");
      grid.className = "gsf-resource-assistant__map-grid";
      recommendations.forEach((item, index) => grid.appendChild(recommendationCard(item, index)));
      container.appendChild(grid);
    }
    addList(container, "Important notes", payload.warnings, "gsf-resource-assistant__warnings");
  }

  document.querySelectorAll("[data-gsf-resource-assistant]").forEach((assistant) => {
    const form = assistant.querySelector("form");
    const situation = form.querySelector('[name="situation"]');
    const status = assistant.querySelector(".gsf-resource-assistant__status");
    const results = assistant.querySelector(".gsf-resource-assistant__results");
    const traditionalSearch = assistant.querySelector(".gsf-resource-assistant__traditional-search");
    const traditionalSearchLink = traditionalSearch?.querySelector("a");

    function updateTraditionalSearch(query) {
      if (!traditionalSearch || !traditionalSearchLink || !query) return;
      const url = new URL(config.traditionalSearchUrl || "/", window.location.origin);
      url.searchParams.set("s", query);
      url.searchParams.set("traditional_search", "1");
      traditionalSearchLink.href = url.toString();
      traditionalSearch.hidden = false;
    }

    form.querySelectorAll("[data-gsf-suggestion]").forEach((suggestion) => {
      suggestion.addEventListener("click", () => {
        situation.value = suggestion.dataset.gsfSuggestion || "";
        situation.focus();
      });
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submit = form.querySelector('button[type="submit"]');
      const payload = { situation: situation.value.trim() };
      updateTraditionalSearch(payload.situation);

      submit.disabled = true;
      form.setAttribute("aria-busy", "true");
      status.textContent = config.labels?.loading || "Mapping useful Hub content…";
      results.replaceChildren();
      try {
        const response = await fetch(config.endpoint, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || "Request failed");
        status.textContent = "";
        renderResults(results, body);
      } catch (error) {
        status.textContent = error.message || config.labels?.error || "The request could not be completed.";
      } finally {
        submit.disabled = false;
        form.removeAttribute("aria-busy");
      }
    });

    const initialQuery = new URL(window.location.href).searchParams.get("q");
    if (initialQuery && !situation.value.trim()) {
      situation.value = initialQuery.slice(0, 4000);
      form.requestSubmit();
    }
  });
})();
