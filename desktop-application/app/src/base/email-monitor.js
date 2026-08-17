/**
 * Email Monitor
 *
 * Detects email-related activity by watching active-window events.
 * When an email client window is focused with a title that suggests a
 * compose/read/send event, emits an 'email-detected' event so the
 * task-tracker can forward the data to the backend.
 *
 * Supported clients (by executable name):
 *   - Microsoft Outlook  (OUTLOOK.EXE / Microsoft Outlook)
 *   - Mozilla Thunderbird (thunderbird / Thunderbird)
 *   - Apple Mail         (Mail)
 *   - Windows Mail / Outlook new (olk.exe)
 *   - Postbox            (postbox)
 *   - eM Client          (em client.exe / EmClient)
 */

const { EventEmitter } = require('events');

// Map executable names (lower-cased) → display names
const EMAIL_CLIENT_MAP = {
  'outlook.exe': 'Microsoft Outlook',
  'microsoft outlook': 'Microsoft Outlook',
  'olk.exe': 'Microsoft Outlook (new)',
  'thunderbird': 'Mozilla Thunderbird',
  'thunderbird.exe': 'Mozilla Thunderbird',
  'mail': 'Apple Mail',
  'mail.app': 'Apple Mail',
  'postbox': 'Postbox',
  'postbox.exe': 'Postbox',
  'emclient.exe': 'eM Client',
  'em client.exe': 'eM Client',
};

// Patterns in window titles that hint at a compose or read window
// (as opposed to the main mailbox list view)
const COMPOSE_TITLE_PATTERNS = [
  /^(?:RE:|FW:|FWD:|AW:|WG:)\s+/i,    // replies / forwards
  /\s*[-–—]\s*Message\s*\(/i,          // Outlook read view: "Subject - Message (HTML)"
  /\s*[-–—]\s*Compose/i,               // compose window in some clients
  /\s*[-–—]\s*New Message/i,
  /\s*\|\s*Compose/i,
  /^\s*New Email\s*$/i,
  /^\s*Compose\s*(?:New)?\s*(?:Message|Email)?\s*$/i,
  /^\s*Write:/i,                        // Thunderbird compose prefix
  /\s*[-–—]\s*(?:Mozilla\s+)?Thunderbird\s*$/i, // any non-inbox Thunderbird window
];

// Window titles that indicate only the mailbox list is open
// (we don't want to log these)
const IGNORE_TITLE_PATTERNS = [
  /^\s*Inbox\s*[-–—]/i,
  /^\s*(?:Microsoft\s+)?Outlook\s*$/i,
  /^\s*Mozilla\s+Thunderbird\s*$/i,
  /^\s*Apple Mail\s*$/i,
];

/**
 * Attempt to infer direction from the window title.
 * Returns 'sent', 'received', or 'unknown'.
 * @param {string} title
 * @returns {'sent'|'received'|'unknown'}
 */
function inferDirection(title) {
  if (/^(?:New\s+(?:Email|Message)|Compose|Write:)/i.test(title)) return 'sent';
  if (/^(?:RE:|FW:|FWD:|AW:|WG:)/i.test(title)) return 'received';
  if (/[-–—]\s*Message\s*\(/i.test(title)) return 'received';
  return 'unknown';
}

/**
 * Strip client suffix like " - Microsoft Outlook" or " - Thunderbird" from the title
 * to extract the subject portion.
 * @param {string} title
 * @param {string} clientName
 * @returns {string}
 */
function extractSubject(title, clientName) {
  let subject = title
    // Remove trailing " - Client Name" suffixes
    .replace(/\s*[-–—]\s*(Microsoft Outlook|Mozilla Thunderbird|Thunderbird|Apple Mail|Postbox|eM Client)[^-–—]*$/i, '')
    // Remove "(HTML)" or "(Plain Text)" Outlook markers
    .replace(/\s*\(HTML\)\s*$/i, '')
    .replace(/\s*\(Plain\s+Text\)\s*$/i, '')
    // Remove "Write:" prefix from Thunderbird
    .replace(/^Write:\s*/i, '')
    .trim();

  return subject || title;
}

class EmailMonitor extends EventEmitter {

  constructor() {
    super();
    this._lastEmittedKey = null;    // debounce: avoid duplicate events for same window
    this._onWindowUpdated = this._onWindowUpdated.bind(this);
    this.active = false;
  }

  /**
   * Determine whether an executable / window title belongs to an email client.
   * Returns the display name or null.
   * @param {string} executable
   * @param {string} title
   * @returns {string|null}
   */
  _resolveClient(executable, title) {
    // Check executable map first (most reliable)
    const execLower = (executable || '').toLowerCase().trim();
    if (EMAIL_CLIENT_MAP[execLower]) return EMAIL_CLIENT_MAP[execLower];

    // Fallback: check if title contains a known client name
    for (const [, displayName] of Object.entries(EMAIL_CLIENT_MAP)) {
      if (title && title.toLowerCase().includes(displayName.toLowerCase())) {
        return displayName;
      }
    }

    return null;
  }

  /**
   * Returns true if the window title looks like an email event worth recording.
   * @param {string} title
   * @returns {boolean}
   */
  _isEmailEvent(title) {
    if (!title) return false;

    // Reject generic mailbox-list windows
    for (const pattern of IGNORE_TITLE_PATTERNS) {
      if (pattern.test(title)) return false;
    }

    // Accept if any compose / read pattern matches
    for (const pattern of COMPOSE_TITLE_PATTERNS) {
      if (pattern.test(title)) return true;
    }

    return false;
  }

  /**
   * Handle active-window 'updated' event.
   * @param {{ title: string, executable: string, url?: string }} window
   */
  _onWindowUpdated(window) {
    if (!window) return;

    const { title = '', executable = '' } = window;
    const clientName = this._resolveClient(executable, title);

    if (!clientName) return;
    if (!this._isEmailEvent(title)) return;

    // Build a dedup key so we don't re-emit for the same focused window
    const dedupKey = `${clientName}|${title}`;
    if (dedupKey === this._lastEmittedKey) return;
    this._lastEmittedKey = dedupKey;

    const subject = extractSubject(title, clientName);
    const direction = inferDirection(title);

    this.emit('email-detected', {
      email_client: clientName,
      direction,
      subject,
      from_address: null,
      to_addresses: [],
      has_attachment: false,
      email_datetime: new Date().toISOString(),
    });
  }

  /**
   * Start listening for active-window updates.
   * @param {EventEmitter} activeWindowEmitter  The active-window module instance.
   */
  start(activeWindowEmitter) {
    if (this.active) return;
    activeWindowEmitter.on('updated', this._onWindowUpdated);
    this._activeWindowRef = activeWindowEmitter;
    this.active = true;
  }

  /**
   * Stop listening.
   */
  stop() {
    if (!this.active) return;
    if (this._activeWindowRef) {
      this._activeWindowRef.off('updated', this._onWindowUpdated);
      this._activeWindowRef = null;
    }
    this._lastEmittedKey = null;
    this.active = false;
  }

}

module.exports = new EmailMonitor();
