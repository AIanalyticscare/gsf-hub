# Multilingual Page Code Editor Snippets

Use these in the WordPress block editor: open the page, choose **Options > Code editor**, replace the current block code, then **Update**.

For Learn, Resources, Case Studies, and Forum, set the page template to **Content Canvas** where available. For Data Centre, the shortcode is the important part.

## Learn

### Spanish `/es/aprender/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--learning","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--learning"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Aprendizaje</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Desarrolle habilidades practicas para la conservacion con enfoque de genero.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Acceda a cursos autoguiados, haga seguimiento del progreso, complete encuestas y obtenga certificados a traves de la plataforma de aprendizaje del GSF Hub.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#available-courses">Ver cursos</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section" id="available-courses"><!-- wp:shortcode -->
[gsf_lms_catalog]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### French `/fr/apprendre/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--learning","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--learning"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Apprentissage</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Renforcez les competences pratiques pour une conservation sensible au genre.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Accedez a des cours en autonomie, suivez les progres, completez les evaluations et obtenez des certificats sur la plateforme d'apprentissage du GSF Hub.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#available-courses">Voir les cours</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section" id="available-courses"><!-- wp:shortcode -->
[gsf_lms_catalog]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### Dutch `/nl/leren/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--learning","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--learning"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Leren</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Bouw praktische vaardigheden voor genderslim natuurbeheer.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Volg cursussen op eigen tempo, houd voortgang bij, voltooi feedback en ontvang certificaten via het leerplatform van de GSF Hub.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#available-courses">Bekijk cursussen</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section" id="available-courses"><!-- wp:shortcode -->
[gsf_lms_catalog]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

## Resources

### Spanish `/es/recursos/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--resources","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--resources"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Recursos</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Herramientas listas para usar en el trabajo de campo.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Explore guias, plantillas, videos y materiales practicos para apoyar la conservacion con enfoque de genero.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_resource_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### French `/fr/ressources/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--resources","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--resources"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Ressources</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Des outils prets pour le terrain.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Consultez des guides, modeles, videos et supports pratiques pour appuyer la conservation sensible au genre.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_resource_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### Dutch `/nl/bronnen/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--resources","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--resources"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Bronnen</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Praktische hulpmiddelen voor gebruik in het veld.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Bekijk gidsen, sjablonen, videos en praktische materialen voor genderslim natuurbeheer.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_resource_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

## Case Studies

### Spanish `/es/estudios-de-caso/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--case-studies","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--case-studies"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Estudios de caso</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Muestre resultados mediante historias utiles.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Explore historias practicas de conservacion con enfoque de genero y resiliencia climatica en el Caribe.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_featured_case_study]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_case_study_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### French `/fr/etudes-de-cas/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--case-studies","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--case-studies"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Etudes de cas</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Presentez les resultats a travers des histoires utiles.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Parcourez des recits pratiques sur la conservation sensible au genre et la resilience climatique dans les Caraibes.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_featured_case_study]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_case_study_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### Dutch `/nl/casestudies/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--case-studies","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--case-studies"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Casestudies</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Toon resultaten met verhalen die praktisch bruikbaar zijn.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Bekijk praktische verhalen over genderslim natuurbeheer en klimaatbestendigheid in het Caribisch gebied.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_featured_case_study]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[gsf_case_study_library]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

## Data Centre

### Spanish `/es/centro-de-datos/`

```html
<!-- wp:shortcode -->
[gsf_data_centre]
<!-- /wp:shortcode -->
```

### French `/fr/centre-de-donnees/`

```html
<!-- wp:shortcode -->
[gsf_data_centre]
<!-- /wp:shortcode -->
```

### Dutch `/nl/datacentrum/`

```html
<!-- wp:shortcode -->
[gsf_data_centre]
<!-- /wp:shortcode -->
```

## Forum

### Spanish `/es/foro/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--forum","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--forum"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Foro comunitario</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Foro</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Participe en conversaciones, haga preguntas practicas y comparta aprendizajes con la comunidad GSF.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[bbp-forum-index]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### French `/fr/forum/`

```html
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--forum","layout":{"type":"flow"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--forum"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Forum communautaire</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Forum</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">Participez aux discussions, posez des questions pratiques et partagez les apprentissages avec la communaute GSF.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><!-- wp:shortcode -->
[bbp-forum-index]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
```

### Dutch `/nl/forum/`

```html
professional
```
