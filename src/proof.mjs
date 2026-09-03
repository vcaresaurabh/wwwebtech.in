/* ============================================================
   Re-exports the build-time proof flags, plus the phrases derived
   from them. Pages import from here rather than from each other.
   Edit the values in site/assets/js/proof-config.js.
   ============================================================ */
export {
  PROOF, PROJECTS, FOUNDED, LAUNCH_WEEKS, RETENTION,
  CLIENTS, CASES, QUOTES, CREDENTIALS,
} from '../site/assets/js/proof-config.js';
import { LAUNCH_WEEKS } from '../site/assets/js/proof-config.js';

/* [V:4] Reads "4–6 weeks" once a range is set; until then it stays
   honestly vague rather than inventing a number. */
export const weeksPhrase = LAUNCH_WEEKS ? `${LAUNCH_WEEKS} weeks` : 'a few weeks';
