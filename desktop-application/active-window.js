const { EventEmitter } = require('events');
const { execFile } = require('child_process');
const Log = require('../utils/log');

const log = new Log('ActiveWindow');

/**
 * Polling interval
 * @type {number} Delay between polls in milliseconds
 */
const ACTIVE_WINDOW_POLLING_INTERVAL = 5000;
const IGNORED_WINDOWS_EXECUTABLES = ['lockapp.exe'];

class ActiveWindow extends EventEmitter {

  constructor() {

    super();

    /**
     * Interval ID of polling timer
     * @type {Number|null}
     */
    this.pollingTimerId = null;

    /**
     * Current application parameters
     * @type {Object}
     */
    this.currentApplication = {
      executable: null,
      title: null,
      url: null,
    };

    /**
     * Optional active window provider
     * @type {Function|null}
     */
    this.provider = null;

    /**
     * Has the provider load been attempted?
     * @type {Boolean}
     */
    this.providerLoadAttempted = false;

    /**
     * Disable app monitoring after unrecoverable native failures
     * @type {Boolean}
     */
    this.monitoringDisabled = false;

    /**
     * Human-readable disable reason
     * @type {String|null}
     */
    this.disableReason = null;

    /**
     * Consecutive polling errors counter.
     * @type {number}
     */
    this.pollErrorCount = 0;

    /**
     * Consecutive error threshold before monitoring is disabled.
     * @type {number}
     */
    this.maxPollErrors = 5;

  }

  /**
   * Timer status
   * @type {boolean}
   */
  get active() {

    return this.pollingTimerId !== null;

  }

  /**
   * Can app monitoring run on this machine?
   * @returns {boolean}
   */
  canMonitor() {

    return Boolean(this.getProvider());

  }

  /**
   * Loads active window provider lazily so native load failures stay isolated
   * @returns {Function|null}
   */
  getProvider() {

    if (this.monitoringDisabled)
      return null;

    if (this.providerLoadAttempted)
      return this.provider;

    this.providerLoadAttempted = true;

    if (process.platform === 'win32') {

      this.provider = this.createWindowsFallbackProvider();
      if (this.provider) {
        log.debug('Windows app monitoring fallback loaded');
        return this.provider;
      }

    }

    try {

      // active-win is optional and may fail to load on incompatible Windows builds
      this.provider = require('active-win');
      log.debug('App monitoring provider loaded');
      return this.provider;

    } catch (err) {

      if (process.platform === 'win32') {

        this.provider = this.createWindowsFallbackProvider(err);
        if (this.provider) {
          log.warning(`Native app monitoring provider failed to load; using Windows fallback (${err.message})`);
          return this.provider;
        }

      }

      this.disable(err, 'App monitoring provider failed to load');
      return null;

    }

  }

  /**
   * Creates a Windows fallback provider using PowerShell and Win32 APIs.
   * This keeps app monitoring alive when active-win native dependencies fail.
   * @param {Error} nativeError active-win load error
   * @returns {Function|null}
   */
  createWindowsFallbackProvider(nativeError) {

    if (process.platform !== 'win32')
      return null;

    const script = `
Add-Type @"
using System;
using System.Text;
using System.Runtime.InteropServices;
public class ActiveWindowReader {
  [DllImport("user32.dll")]
  public static extern IntPtr GetForegroundWindow();
  [DllImport("user32.dll", CharSet = CharSet.Unicode)]
  public static extern int GetWindowText(IntPtr hWnd, StringBuilder text, int count);
  [DllImport("user32.dll")]
  public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint processId);
}
"@
$handle = [ActiveWindowReader]::GetForegroundWindow()
if ($handle -eq [IntPtr]::Zero) { throw "No foreground window" }
$titleBuilder = New-Object System.Text.StringBuilder 1024
[void][ActiveWindowReader]::GetWindowText($handle, $titleBuilder, $titleBuilder.Capacity)
$processId = 0
[void][ActiveWindowReader]::GetWindowThreadProcessId($handle, [ref]$processId)
$process = Get-Process -Id $processId -ErrorAction Stop
$path = $null
try { $path = $process.MainModule.FileName } catch { $path = $process.ProcessName + ".exe" }
$url = $null
if ($process.ProcessName -match '^(chrome|msedge|firefox|brave|opera|vivaldi)$') {
  try {
    Add-Type -AssemblyName UIAutomationClient
    Add-Type -AssemblyName UIAutomationTypes
    $root = [System.Windows.Automation.AutomationElement]::FromHandle($handle)
    $editCondition = New-Object System.Windows.Automation.PropertyCondition(
      [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
      [System.Windows.Automation.ControlType]::Edit
    )
    $elements = $root.FindAll([System.Windows.Automation.TreeScope]::Descendants, $editCondition)
    foreach ($element in $elements) {
      $valuePattern = $null
      $value = $null
      if ($element.TryGetCurrentPattern([System.Windows.Automation.ValuePattern]::Pattern, [ref]$valuePattern)) {
        $value = $valuePattern.Current.Value
      }
      if (-not $value) {
        $value = $element.Current.Name
      }
      if (-not $value) {
        continue
      }
      $candidate = $value.Trim()
      if ($candidate -match '^https?://') {
        $url = $candidate
        break
      }
      if ($candidate -match '^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$') {
        $url = "https://$candidate"
        break
      }
    }
  } catch {}
}
[PSCustomObject]@{
  title = $titleBuilder.ToString()
  url = $url
  owner = @{
    name = if ($process.ProcessName) { $process.ProcessName + ".exe" } else { $path }
    path = $path
    processId = $processId
  }
} | ConvertTo-Json -Compress
`;

    return () => new Promise((resolve, reject) => {

      execFile(
        'powershell.exe',
        ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', script],
        { windowsHide: true, timeout: ACTIVE_WINDOW_POLLING_INTERVAL - 1000 },
        (error, stdout, stderr) => {

          if (error) {
            error.message = `${error.message}${stderr ? ` (${stderr.trim()})` : ''}`;
            reject(error);
            return;
          }

          try {
            resolve(JSON.parse(stdout));
          } catch (parseError) {
            const reason = nativeError && nativeError.message
              ? ` after native provider failed (${nativeError.message})`
              : '';
            parseError.message = `Unable to parse Windows fallback output${reason}: ${parseError.message}`;
            reject(parseError);
          }

        },
      );

    });

  }

  /**
   * Disables app monitoring after an unrecoverable failure
   * @param {Error} [err] native provider error
   * @param {String} [message] human-readable failure message
   */
  disable(err, message = 'App monitoring disabled') {

    if (this.pollingTimerId) {

      clearInterval(this.pollingTimerId);
      this.pollingTimerId = null;

    }

    if (this.monitoringDisabled)
      return;

    this.monitoringDisabled = true;
    this.disableReason = err && err.message ? err.message : message;

    if (err)
      log.error(message, err);
    else
      log.warning(message);

    this.emit('unavailable', { reason: this.disableReason });

  }

  /**
   * Returns unavailability reason if app monitoring has been disabled
   * @returns {String|null}
   */
  getDisableReason() {

    return this.disableReason;

  }

  /**
   * Starts active window polling
   * @returns {boolean} True if successfully started, False otherwise
   */
  start() {

    if (this.active)
      return false;

    const provider = this.getProvider();

    if (!provider)
      return false;

    const pollOnce = async () => {

      try {

        const window = await provider();
        this.pollErrorCount = 0;

        const hasWindowMetadata = Boolean(
          window && (
            (window.owner && (window.owner.path || window.owner.name))
            || window.title
            || window.url
          )
        );

        if (!hasWindowMetadata) {

          log.debug('Active window snapshot ignored: no metadata');
          return;

        }

        const executable = (
          (window.owner && window.owner.path)
          || (window.owner && window.owner.name)
          || window.title
          || window.url
          || 'unknown'
        );

        const normalizedWindow = {
          ...window,
          executable: String(executable).trim() || 'unknown',
          title: String(window.title || ''),
          url: window.url || null,
        };

        if (this.shouldIgnoreWindow(normalizedWindow)) {

          log.debug(`Active window snapshot ignored: ${normalizedWindow.executable}`);
          return;

        }

        // Detect changes, including first successful snapshot.
        if (
          normalizedWindow.executable !== this.currentApplication.executable
          || normalizedWindow.title !== this.currentApplication.title
          || normalizedWindow.url !== this.currentApplication.url
        ) {

          this.applyNewWindow(normalizedWindow);

        }

      } catch (err) {

        this.pollErrorCount += 1;

        if (this.pollErrorCount >= this.maxPollErrors) {
          this.disable(err, 'Error occured during active window poll');
          return;
        }

        log.warning(`Active window poll failed (${this.pollErrorCount}/${this.maxPollErrors})`);

      }

    };

    // Prime current window state immediately so reports are not empty until user switches windows.
    pollOnce();

    this.pollingTimerId = setInterval(async () => {

      await pollOnce();

    }, ACTIVE_WINDOW_POLLING_INTERVAL);
    return true;

  }

  /**
   * Stops the active window polling
   */
  stop() {

    if (!this.pollingTimerId)
      return;

    clearInterval(this.pollingTimerId);
    this.pollingTimerId = null;

  }

  /**
   * Update current window
   * @private
   * @param {Object} window New window definition
   */
  applyNewWindow(window) {

    const executable = (
      window.executable
      ||
      (window.owner && window.owner.path)
      || (window.owner && window.owner.name)
      || window.title
      || window.url
      || 'unknown'
    );

    this.currentApplication.executable = String(executable).trim() || 'unknown';
    this.currentApplication.title = String(window.title || '');
    this.currentApplication.url = window.url || null;
    this.emit('updated', this.currentApplication);

  }

  /**
   * Return normalized executable filename.
   * @param {String} executable Full path or process name
   * @returns {String}
   */
  getExecutableName(executable) {

    return String(executable || '')
      .split(/[\\/]/)
      .pop()
      .trim()
      .toLowerCase();

  }

  /**
   * Checks if a window snapshot should be excluded from tracking.
   * @param {Object} window Normalized active-window snapshot
   * @returns {Boolean}
   */
  shouldIgnoreWindow(window) {

    const executableName = this.getExecutableName(window && window.executable);

    return IGNORED_WINDOWS_EXECUTABLES.includes(executableName);

  }

}

module.exports = new ActiveWindow();
