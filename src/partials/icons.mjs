/* ============================================================
   One icon set. 24px grid, 1.5px stroke, currentColor, no fill.
   Emitted once per page as a <symbol> sprite; referenced with <use>.
   No icon fonts, no second library, no emoji-as-icons. (§9.1)
   ============================================================ */
const P = {
  /* --- the twelve sub-services ---------------------------------- */
  websites:  '<rect x="2.75" y="4.25" width="18.5" height="15.5" rx="2"/><path d="M2.75 8.75h18.5M5.75 6.5h.01M8.25 6.5h.01"/>',
  ecommerce: '<path d="M4.5 7.75h15l-1.15 10.1a2 2 0 0 1-2 1.9H7.65a2 2 0 0 1-2-1.9L4.5 7.75Z"/><path d="M8.75 10.5V7a3.25 3.25 0 0 1 6.5 0v3.5"/>',
  crm:       '<rect x="2.75" y="4.25" width="18.5" height="15.5" rx="2"/><path d="M8.75 4.25v15.5M15.25 4.25v15.5M4.75 8.5h2M11 8.5h2M11 12h2M17.25 8.5h2"/>',
  automation:'<circle cx="5.75" cy="6.25" r="2.25"/><circle cx="18.25" cy="17.75" r="2.25"/><circle cx="18.25" cy="6.25" r="2.25"/><path d="M8 6.25h8M5.75 8.5v6a3.25 3.25 0 0 0 3.25 3.25h7"/>',
  techseo:   '<path d="M3.5 17.5a9 9 0 1 1 17 0"/><path d="M12 17.5 16.25 10"/><circle cx="12" cy="17.5" r="1.15"/>',
  content:   '<path d="M5.25 2.75h8.5l5 5v13.5H5.25V2.75Z"/><path d="M13.5 2.75v5.25h5.25M8.5 12.5h7M8.5 16.25h4.5"/>',
  local:     '<path d="M19 10.25c0 5-7 10.5-7 10.5s-7-5.5-7-10.5a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  geo:       '<path d="M12 2.75 13.9 8.1 19.25 10l-5.35 1.9L12 17.25l-1.9-5.35L4.75 10l5.35-1.9L12 2.75Z"/><path d="m18.25 16.5.75 2.05 2.05.75-2.05.75-.75 2.05-.75-2.05-2.05-.75 2.05-.75.75-2.05Z"/>',
  organic:   '<path d="M20.25 12.75c0 3.6-3.7 6.5-8.25 6.5a9.9 9.9 0 0 1-2.65-.35L4.5 20.5l1.3-3.6a6.1 6.1 0 0 1-2.05-4.15c0-3.6 3.7-6.5 8.25-6.5s8.25 2.9 8.25 6.5Z"/><path d="M8.75 12.5h.01M12 12.5h.01M15.25 12.5h.01"/>',
  paid:      '<circle cx="12" cy="12" r="8.75"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.35"/>',
  socialseo: '<circle cx="10.75" cy="10.75" r="7"/><path d="m16 16 4.75 4.75"/><path d="m9 8.25 4.25 2.5L9 13.25v-5Z"/>',
  creative:  '<rect x="2.75" y="4.75" width="18.5" height="14.5" rx="2.5"/><path d="m10 9.5 5 2.5-5 2.5v-5Z"/>',
  /* --- UI -------------------------------------------------------- */
  'arrow-right':'<path d="M4.5 12h15M13.5 6l6 6-6 6"/>',
  'arrow-down': '<path d="M12 4.5v15M6 13.5l6 6 6-6"/>',
  'arrow-up-right':'<path d="M7 17 17 7M8.5 7H17v8.5"/>',
  chevron:      '<path d="m6 9.5 6 6 6-6"/>',
  check:        '<path d="m4.75 12.5 4.75 4.75L19.25 6.75"/>',
  close:        '<path d="M6 6l12 12M18 6 6 18"/>',
  mail:         '<rect x="2.75" y="5.25" width="18.5" height="13.5" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
  clock:        '<circle cx="12" cy="12" r="9"/><path d="M12 6.75V12l3.5 2.25"/>',
  shield:       '<path d="M12 2.75 20 6v5.5c0 4.8-3.3 8.4-8 9.75-4.7-1.35-8-4.95-8-9.75V6l8-3.25Z"/><path d="m8.75 11.75 2.25 2.25 4.25-4.25"/>',
  /* --- social (line-art, in-system; not official brand marks) ---- */
  whatsapp:  '<path d="M20.25 11.7a8.2 8.2 0 0 1-12.1 7.2L3.75 20.25l1.4-4.3A8.2 8.2 0 1 1 20.25 11.7Z"/><path d="M9.1 8.9c.25-.05.5 0 .65.3l.75 1.55c.1.25.05.5-.1.7l-.4.5a.35.35 0 0 0-.05.4 5.3 5.3 0 0 0 2.7 2.4c.15.05.3 0 .4-.1l.5-.55c.2-.2.45-.25.7-.15l1.6.7c.3.15.4.4.35.65-.15.85-.9 1.5-1.8 1.5-2.95 0-5.9-2.95-5.9-5.9 0-.9.65-1.65 1.5-1.8Z"/>',
  linkedin:  '<rect x="3.25" y="3.25" width="17.5" height="17.5" rx="3"/><path d="M7.5 10.5v6.25M7.5 7.6v.01M11.5 16.75V10.5M11.5 13.1c0-1.45.9-2.35 2.15-2.35s2.1.9 2.1 2.35v3.65"/>',
  instagram: '<rect x="3.25" y="3.25" width="17.5" height="17.5" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M16.9 7.1h.01"/>',
};

export const ICON_KEYS = Object.keys(P);

/** The sprite. Rendered once, just before </body>. */
export const sprite = () =>
  `<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>` +
  Object.entries(P).map(([k, v]) => `<symbol id="i-${k}" viewBox="0 0 24 24">${v}</symbol>`).join('') +
  `</defs></svg>`;

/** Decorative by default — icons here never carry meaning text doesn't.
    An unknown name fails the build rather than rendering an empty box. */
export const icon = (name, cls = '') => {
  if (!(name in P)) throw new Error(`icon: no such icon "${name}". Have: ${ICON_KEYS.join(', ')}`);
  return `<svg class="ico${cls ? ' ' + cls : ''}" aria-hidden="true" focusable="false"><use href="#i-${name}"/></svg>`;
};

/** The travelling arrow used inside links and buttons. */
export const arw = (name = 'arrow-right') => {
  if (!(name in P)) throw new Error(`arw: no such icon "${name}"`);
  return `<svg class="ico ico--sm arw" aria-hidden="true" focusable="false"><use href="#i-${name}"/></svg>`;
};
