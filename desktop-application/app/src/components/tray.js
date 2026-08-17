const { Tray, Menu, app } = require('electron');
const Logger = require('../utils/log');
const osIntegration = require('../base/os-integration');
const taskController = require('../controller/tasks');
const tracker = require('../base/task-tracker');
const authentication = require('../base/authentication');
const userPreferences = require('../base/user-preferences');
const translation = require('../base/translation');

const log = new Logger('Tray');

const MAX_TASK_TEXT_LENGTH = 48;

const ICONS_PATHS = require('../utils/icons');

const setMacDockIcon = icon => {

  if (process.platform === 'darwin' && app.dock && app.dock.setIcon)
    app.dock.setIcon(icon);

};

const tray = new Tray(ICONS_PATHS.tray.LOADING);
tray.setToolTip('PIN-Tracker');
setMacDockIcon(ICONS_PATHS.dock.LOADING);

let selectedTaskId = null;
let availableTasks = {};

const MENU_ITEMS = {
  HIDE: 0,
  SHOW: 1,
  STATUS: 3,
  TASKS: 4,
};

const trayMenuElements = [
  {
    label: 'Hide',
    type: 'normal',
    visible: true,
    click: () => {

      osIntegration.windowHideRequest();

    },
  },
  {
    label: 'Show',
    type: 'normal',
    visible: false,
    click: () => {

      osIntegration.windowFocus();

    },
  },
  {
    type: 'separator',
  },
  {
    label: 'Tracking is active',
    type: 'normal',
    visible: false,
    enabled: false,
  },
  {
    label: 'Available Tasks',
    type: 'submenu',
    visible: false,
    submenu: [],
  },
  {
    type: 'separator',
    visible: false,
  },
  {
    label: 'Quit',
    type: 'normal',
    visible: true,
    click: () => osIntegration.gracefullExit(),
  },
];

const buildMenuWithTranslations = origMenuElements => {

  const menuElements = [];
  origMenuElements.forEach(element => {

    const index = menuElements.push({ ...element });
    if (element.label)
      menuElements[index - 1].label = translation.translate(element.label);

  });
  return Menu.buildFromTemplate(menuElements);

};

let trayMenu = buildMenuWithTranslations(trayMenuElements);
tray.setContextMenu(trayMenu);

tray.on('click', e => {

  if (e.ctrlKey)
    return;

  if (osIntegration.windowIsMinimized() && process.platform !== 'darwin')
    trayMenu.items[MENU_ITEMS.SHOW].click();
  else if (process.platform !== 'darwin')
    trayMenu.items[MENU_ITEMS.HIDE].click();

});

const onMinimize = () => {

  trayMenu.items[MENU_ITEMS.HIDE].visible = false;
  trayMenu.items[MENU_ITEMS.SHOW].visible = true;
  tray.setContextMenu(trayMenu);

};

const onRestore = () => {

  trayMenu.items[MENU_ITEMS.HIDE].visible = true;
  trayMenu.items[MENU_ITEMS.SHOW].visible = false;
  tray.setContextMenu(trayMenu);

};

osIntegration.window.on('minimize', onMinimize);
osIntegration.window.on('hide', onMinimize);
osIntegration.window.on('restore', onRestore);
osIntegration.window.on('show', onRestore);


const onStartTrack = taskId => {

  selectedTaskId = taskId;
  tray.setImage(ICONS_PATHS.tray.TRACKING);
  setMacDockIcon(ICONS_PATHS.dock.TRACKING);

  trayMenu.items[MENU_ITEMS.STATUS].visible = true;

  trayMenu.items[MENU_ITEMS.TASKS].submenu.items.forEach(menuItem => {

    menuItem.checked = false; // eslint-disable-line no-param-reassign

  });

  trayMenu.items[MENU_ITEMS.TASKS].submenu.items[0].visible = false;

  if (Object.prototype.hasOwnProperty.call(availableTasks, taskId))
    trayMenu.items[MENU_ITEMS.TASKS].submenu.items[availableTasks[taskId]].checked = true;

  tray.setContextMenu(trayMenu);

};

const onStopTrack = () => {

  tray.setImage(ICONS_PATHS.tray.IDLE);
  setMacDockIcon(ICONS_PATHS.dock.IDLE);
  trayMenu.items[MENU_ITEMS.STATUS].visible = false;
  tray.setContextMenu(trayMenu);

};

taskController.events.on('pulled', tasksReference => {

  let tasks = null;

  // Filter only active task if corresponding flag in config is set
  if (userPreferences.get('showInactiveTasks') === false)
    tasks = tasksReference.filter(task => task.status === '1');

  // Dereference tasks
  else
    tasks = tasksReference.slice();

  tray.setImage(ICONS_PATHS.tray.IDLE);
  setMacDockIcon(ICONS_PATHS.dock.IDLE);

  availableTasks = {};
  trayMenuElements[MENU_ITEMS.TASKS].submenu = [];
  trayMenuElements[MENU_ITEMS.TASKS].submenu.push({
    id: '--nothing--',
    label: translation.translate('- Nothing -'),
    type: 'radio',
    click: () => {

      if (tracker.active)
        return;

      if (selectedTaskId !== null) {

        selectedTaskId = null;
        onStopTrack();

      }

    },
  });

  tasks.forEach((task, index) => {

    trayMenuElements[MENU_ITEMS.TASKS].submenu.push({
      id: task.id,
      label: task.name.substr(0, MAX_TASK_TEXT_LENGTH) + (task.name.length > MAX_TASK_TEXT_LENGTH ? '...' : ''),
      type: 'radio',
      click: () => {

        if (selectedTaskId !== task.id)
          tracker.start(task.id);

      },
    });
    availableTasks[task.id] = index + 1;

  });

  trayMenu.items.forEach((menuItem, index) => {

    trayMenuElements[index].visible = menuItem.visible;

  });

  trayMenuElements[MENU_ITEMS.TASKS].visible = !!tasks.length;
  trayMenuElements[MENU_ITEMS.TASKS + 1].visible = trayMenuElements[MENU_ITEMS.TASKS].visible;

  tray.setContextMenu(trayMenu = buildMenuWithTranslations(trayMenuElements));

  if (tracker.active && selectedTaskId !== null)
    onStartTrack(selectedTaskId);
  else if (!tracker.active && selectedTaskId !== null)
    onStopTrack();

});

tracker.on('started', onStartTrack);
tracker.on('switching', () => {

  tray.setImage(ICONS_PATHS.tray.LOADING);
  setMacDockIcon(ICONS_PATHS.dock.LOADING);

});
tracker.on('switched', onStartTrack);
tracker.on('stopping', () => {

  tray.setImage(ICONS_PATHS.tray.LOADING);
  setMacDockIcon(ICONS_PATHS.dock.LOADING);

});
tracker.on('stopped', onStopTrack);

authentication.events.on('logged-out', () => {

  tray.setImage(ICONS_PATHS.tray.IDLE);
  setMacDockIcon(ICONS_PATHS.tray.IDLE);
  trayMenu.items[MENU_ITEMS.STATUS].visible = false;
  trayMenu.items[MENU_ITEMS.TASKS].visible = false;
  trayMenu.items[MENU_ITEMS.TASKS + 1].visible = false;
  tray.setContextMenu(trayMenu);

});

translation.on('language-changed', () => {

  trayMenu.items.forEach((menuItem, index) => {

    trayMenuElements[index].visible = menuItem.visible;
    trayMenuElements[index].checked = menuItem.checked;

  });

  trayMenu = buildMenuWithTranslations(trayMenuElements);

  trayMenu.items[MENU_ITEMS.TASKS].submenu.items.forEach(menuItem => {

    menuItem.checked = false; // eslint-disable-line no-param-reassign

  });

  trayMenu.items[MENU_ITEMS.TASKS].submenu.items[0].visible = selectedTaskId === null;

  if (selectedTaskId && Object.prototype.hasOwnProperty.call(availableTasks, selectedTaskId))
    trayMenu.items[MENU_ITEMS.TASKS].submenu.items[availableTasks[selectedTaskId]].checked = true;
  else {

    trayMenu.items[MENU_ITEMS.TASKS].submenu.items[0].visible = true;
    trayMenu.items[MENU_ITEMS.TASKS].submenu.items[0].checked = true;

  }

  tray.setContextMenu(trayMenu);

});

log.debug('Loaded');
