const slogans = ['Pin-Tracker - workforce activity tracking', 'Manage your time with ease'];

const getRandomInt = max => {
    return Math.floor(Math.random() * Math.floor(max));
};

export default () => {
    return slogans[getRandomInt(slogans.length)];
};
