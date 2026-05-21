<script setup>
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { preloadAllTextures } from '@/utils/textures';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

const props = defineProps({
    mayor: {
        type: Object,
        default: () => ({}),
    },
    perkState: {
        type: Object,
        default: () => ({ active_perks: {}, boosted_event_keys: [] }),
    },
    electionTimeline: {
        type: Object,
        default: null,
    },
});

const nowMs = ref(Date.now());
let timerId = null;
let notifyTickId = null;

const localTz = ref(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
const notificationPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'unsupported');
const swRegistration = ref(null);
const supportsTimestampTrigger = ref(typeof window !== 'undefined' && 'TimestampTrigger' in window);
const notifyEnabled = reactive({});
const notifyDeniedFlash = ref(false);
const notifyConfirmKey = ref(null);
const sentKeys = ref(new Set());

const NOTIFY_LEAD_SECONDS = 5 * 60;
const NOTIFY_PREFS_KEY = 'sbh:event-timer:notify-enabled';
const NOTIFY_SENT_KEY = 'sbh:event-timer:notify-sent';

const textureVersion = ref(0);
provide('textureVersion', textureVersion);

const SKYBLOCK_DAYS_PER_MONTH = 31;
const SKYBLOCK_DAY_LENGTH_SECONDS = 20 * 60;
const SKYBLOCK_SECONDS_PER_DAY = 24 * 60 * 60;
const SKYBLOCK_DAYS_PER_YEAR = SKYBLOCK_DAYS_PER_MONTH * 12;
const SKYBLOCK_MONTHS_PER_YEAR = 12;
const SKYBLOCK_EPOCH_MS = new Date('Jun 11 2019 17:55:00 GMT').getTime();
const SKYBLOCK_CALENDAR_EVENT_DAY_OFFSET = -1;
const SKYBLOCK_MONTH_NAMES = [
    'Early Spring',
    'Spring',
    'Late Spring',
    'Early Summer',
    'Summer',
    'Late Summer',
    'Early Autumn',
    'Autumn',
    'Late Autumn',
    'Early Winter',
    'Winter',
    'Late Winter',
];

const SKYBLOCK_EVENT_RULES = [
    { key: 'spookyFestival', name: 'Spooky Festival', when: [{ start: { month: 9, day: 29 }, end: { month: 9, day: 31 } }] },
    { key: 'zoo', name: 'Travelling Zoo', when: [{ start: { month: 4, day: 1 }, end: { month: 4, day: 3 } }, { start: { month: 10, day: 1 }, end: { month: 10, day: 3 } }] },
    { key: 'mythologicalRitual', name: 'Mythological Ritual', when: [{ start: { day: 1 }, end: { day: 31 } }] },
    { key: 'jerryWorkshop', name: "Jerry's Workshop", when: [{ start: { month: 12, day: 1 }, end: { month: 12, day: 31 } }] },
    { key: 'winter', name: 'Season of Jerry', when: [{ start: { month: 12, day: 24 }, end: { month: 12, day: 26 } }] },
    { key: 'newYear', name: 'New Year Celebration', when: [{ start: { month: 12, day: 29 }, end: { month: 12, day: 31 } }] },
    { key: 'interest', name: 'Bank Interest', when: [{ start: { day: 1 }, end: { day: 1 } }] },
    { key: 'electionOver', name: 'Election Over', when: [{ start: { month: 3, day: 27 }, end: { month: 3, day: 27 } }] },
    { key: 'cotfs', name: 'Cult of the Fallen Star', when: [{ start: { day: 7 }, end: { day: 7 } }, { start: { day: 14 }, end: { day: 14 } }, { start: { day: 21 }, end: { day: 21 } }, { start: { day: 28 }, end: { day: 28 } }] },
];

const DWARVEN_KINGS = ['Brammor', 'Emkam', 'Redros', 'Erren', 'Thormyr', 'Emmor', 'Grandan'];

const EVENT_ITEM_OVERRIDES = {
    newYear: { skyblock_id: 'NEW_YEAR_CAKE_BAG', texture_path: '/item/cake', rarity: 'LEGENDARY' },
    spookyFestival: { skyblock_id: 'PET_ITEM_SPOOKY_CUPCAKE', texture_path: '/item/cake', rarity: 'LEGENDARY' },
    zoo: { skyblock_id: 'PET_CAKE', texture_path: '/item/cake', rarity: 'EPIC' },
    mythologicalRitual: { skyblock_id: 'DAEDALUS_AXE', texture_path: '/item/golden_axe', rarity: 'MYTHIC' },
    jerryWorkshop: { texture_path: '/item/snowball', rarity: 'RARE' },
    winter: { texture_path: '/item/snowball', rarity: 'EPIC' },
    electionOver: { texture_path: '/item/record_11', rarity: 'RARE' },
    interest: { texture_path: '/item/gold_ingot', rarity: 'COMMON' },
    cotfs: { skyblock_id: 'GREAT_SPOOK_ARTIFACT', texture_path: '/item/nether_star', rarity: 'MYTHIC' },
    dark_auction: { skyblock_id: 'DARK_QUEENS_SOUL_DROP', texture_path: '/item/record_13', rarity: 'MYTHIC' },
    jacob: { skyblock_id: 'JACOBS_TICKET', texture_path: '/item/paper', rarity: 'EPIC' },
    dwarven_king: { texture_path: '/item/gold_nugget', rarity: 'RARE' },
};

const NOTIFICATION_ICON_MAP = {
    'dark-auction': 'https://sky.coflnet.com/static/icon/DARK_QUEENS_SOUL_DROP',
    'jacobs-contest': 'https://sky.coflnet.com/static/icon/JACOBS_TICKET',
    'traveling-zoo': 'https://sky.coflnet.com/static/icon/PET_CAKE',
    'mythological-ritual': 'https://sky.coflnet.com/static/icon/DAEDALUS_AXE',
    'dungeon-rush': '/img/textures/eye_of_ender.png',
    'spooky-festival': 'https://sky.coflnet.com/static/icon/PET_ITEM_SPOOKY_CUPCAKE',
    'bank-interest': '/img/textures/gold_ingot.png',
    'cult-fallen-star': 'https://sky.coflnet.com/static/icon/GREAT_SPOOK_ARTIFACT',
    'season-of-jerry': '/img/textures/snowball.png',
    'new-year-celebration': 'https://sky.coflnet.com/static/icon/NEW_YEAR_CAKE_BAG',
    'election-over': '/img/textures/record_11.png',
    'election-cycle': '/img/textures/record_11.png',
};

const selectedCalendarDay = ref(1);
const selectedCalendarMonthAbsolute = ref(0);
const displayedMonthOffset = ref(0);
const expandedTimer = ref(null);

const events = [
    {
        key: 'dark-auction',
        name: 'Dark Auction',
        cycleSeconds: 3600,
        activeSeconds: 300,
        offsetSeconds: 55 * 60,
        activeLabel: 'Auction Live',
        upcomingLabel: 'Auction Opens In',
    },
    {
        key: 'jacobs-contest',
        name: "Jacob's Contest",
        cycleSeconds: 3600,
        activeSeconds: 20 * 60,
        offsetSeconds: 15 * 60,
        activeLabel: 'Contest Active',
        upcomingLabel: 'Contest Starts In',
    },
    {
        key: 'traveling-zoo',
        name: 'Traveling Zoo',
        cycleSeconds: 3 * 3600,
        activeSeconds: 60 * 60,
        offsetSeconds: 45 * 60,
        activeLabel: 'Zoo Open',
        upcomingLabel: 'Zoo Arrives In',
    },
    {
        key: 'mythological-ritual',
        name: 'Mythological Ritual',
        cycleSeconds: 2 * 3600,
        activeSeconds: 30 * 60,
        offsetSeconds: 30 * 60,
        activeLabel: 'Ritual Active',
        upcomingLabel: 'Ritual Starts In',
        requiresPerk: 'mythological_ritual',
    },
    {
        key: 'dungeon-rush',
        name: 'Dungeon Rush',
        cycleSeconds: 3600,
        activeSeconds: 15 * 60,
        offsetSeconds: 20 * 60,
        activeLabel: 'Dungeon Window Active',
        upcomingLabel: 'Dungeon Window In',
        dungeonRelated: true,
    },
    {
        key: 'spooky-festival',
        name: 'Spooky Festival',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((8 * SKYBLOCK_DAYS_PER_MONTH) + 28) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Festival Active',
        upcomingLabel: 'Festival Starts In',
    },
    {
        key: 'bank-interest',
        name: 'Bank Interest',
        cycleSeconds: SKYBLOCK_DAYS_PER_MONTH * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 15 * 60,
        offsetSeconds: 0,
        activeLabel: 'Interest Payout Active',
        upcomingLabel: 'Interest Payout In',
    },
    {
        key: 'cult-fallen-star',
        name: 'Cult of the Fallen Star',
        cycleSeconds: 7 * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 15 * 60,
        offsetSeconds: 0,
        activeLabel: 'Fallen Star Window Active',
        upcomingLabel: 'Fallen Star Window In',
    },
    {
        key: 'season-of-jerry',
        name: 'Season of Jerry',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((11 * SKYBLOCK_DAYS_PER_MONTH) + 23) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Season Event Active',
        upcomingLabel: 'Season Event Starts In',
        requiresPerk: 'jerry_workshop',
    },
    {
        key: 'new-year-celebration',
        name: 'New Year Celebration',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((11 * SKYBLOCK_DAYS_PER_MONTH) + 28) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Celebration Active',
        upcomingLabel: 'Celebration Starts In',
    },
    {
        key: 'election-over',
        name: 'Election Over',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((2 * SKYBLOCK_DAYS_PER_MONTH) + 26) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Election Day Active',
        upcomingLabel: 'Election Day In',
    },
];

const activePerks = computed(() => props.perkState?.active_perks ?? {});
const boostedEventKeySet = computed(() => new Set(props.perkState?.boosted_event_keys ?? []));

function isEventVisible(event) {
    if (!event.requiresPerk) {
        return true;
    }

    return Boolean(activePerks.value[event.requiresPerk]);
}

function isEventBoosted(event) {
    if (event.dungeonRelated && activePerks.value.dungeon_benefit) {
        return true;
    }

    return boostedEventKeySet.value.has(event.key);
}

function buildElectionTimerCard(nowSec) {
    const timeline = props.electionTimeline;
    if (!timeline) {
        return null;
    }

    const startUnix = Number(timeline.start_unix ?? 0);
    const endUnix = Number(timeline.end_unix ?? 0);
    const officeUnix = Number(timeline.office_unix ?? 0);

    if (!startUnix || !endUnix || !officeUnix) {
        return null;
    }

    let isActive = false;
    let stateRemaining = 0;
    let stateTotal = 1;
    let nextStart = startUnix;
    let nextEnd = endUnix;
    let activeLabel = 'Election Live';
    let upcomingLabel = 'Election Starts In';

    if (nowSec < startUnix) {
        stateRemaining = startUnix - nowSec;
        stateTotal = Math.max(1, startUnix - nowSec);
        nextStart = startUnix;
        nextEnd = endUnix;
    } else if (nowSec >= startUnix && nowSec < endUnix) {
        isActive = true;
        stateRemaining = endUnix - nowSec;
        stateTotal = Math.max(1, endUnix - startUnix);
        nextStart = endUnix;
        nextEnd = officeUnix;
    } else if (nowSec >= endUnix && nowSec < officeUnix) {
        stateRemaining = officeUnix - nowSec;
        stateTotal = Math.max(1, officeUnix - endUnix);
        nextStart = officeUnix;
        nextEnd = officeUnix;
        activeLabel = 'Mayor Transition Live';
        upcomingLabel = 'New Mayor Takes Office In';
    } else {
        return null;
    }

    const progress = Math.max(0, Math.min(1, 1 - (stateRemaining / stateTotal)));

    return {
        key: 'election-cycle',
        name: 'Election Cycle',
        isActive,
        progress,
        stateRemaining,
        nextStart,
        nextEnd,
        nextOccurrences: [
            { startUnix: nextStart, inSeconds: Math.max(0, nextStart - nowSec) },
            { startUnix: nextEnd, inSeconds: Math.max(0, nextEnd - nowSec) },
            { startUnix: officeUnix, inSeconds: Math.max(0, officeUnix - nowSec) },
        ],
        activeLabel,
        upcomingLabel,
        notifyEnabled: Boolean(notifyEnabled.election_cycle),
        boosted: false,
        dungeonRelated: false,
    };
}

const timerCards = computed(() => {
    const nowSec = Math.floor(nowMs.value / 1000);

    const baseCards = events
        .filter((event) => isEventVisible(event))
        .map((event) => {
            const shifted = nowSec - event.offsetSeconds;
            const cyclePos = ((shifted % event.cycleSeconds) + event.cycleSeconds) % event.cycleSeconds;

            const isActive = cyclePos < event.activeSeconds;
            const inactiveWindow = event.cycleSeconds - event.activeSeconds;

            const secondsUntilStart = isActive ? 0 : (event.cycleSeconds - cyclePos);
            const secondsUntilEnd = isActive
                ? (event.activeSeconds - cyclePos)
                : (event.cycleSeconds - cyclePos + event.activeSeconds);

            const stateTotal = isActive ? event.activeSeconds : inactiveWindow;
            const stateRemaining = isActive ? secondsUntilEnd : secondsUntilStart;
            const progress = stateTotal > 0
                ? Math.max(0, Math.min(1, 1 - (stateRemaining / stateTotal)))
                : 0;

            const nextStart = isActive
                ? nowSec + (event.cycleSeconds - cyclePos)
                : nowSec + secondsUntilStart;
            const nextEnd = nowSec + secondsUntilEnd;

            const nextOccurrences = Array.from({ length: 3 }).map((_, idx) => {
                const startUnix = nextStart + (idx * event.cycleSeconds);
                return {
                    startUnix,
                    inSeconds: startUnix - nowSec,
                };
            });

            return {
                ...event,
                isActive,
                progress,
                stateRemaining,
                nextStart,
                nextEnd,
                nextOccurrences,
                notifyEnabled: Boolean(notifyEnabled[event.key]),
                boosted: isEventBoosted(event),
            };
        });

    const electionCard = buildElectionTimerCard(nowSec);
    if (electionCard) {
        baseCards.push(electionCard);
    }

    return baseCards;
});

function seasonFromMonth(month) {
    if (month <= 3) return 'Spring';
    if (month <= 6) return 'Summer';
    if (month <= 9) return 'Autumn';
    return 'Winter';
}

function getSkyblockDateFromMs(tsMs) {
    const dayMs = SKYBLOCK_DAY_LENGTH_SECONDS * 1000;
    const monthMs = SKYBLOCK_DAYS_PER_MONTH * dayMs;
    const yearMs = SKYBLOCK_DAYS_PER_YEAR * dayMs;
    const deltaMs = Math.max(0, tsMs - SKYBLOCK_EPOCH_MS);

    const year = Math.floor(deltaMs / yearMs) + 1;
    const withinYear = deltaMs % yearMs;
    const month = Math.floor(withinYear / monthMs) + 1;
    const withinMonth = withinYear % monthMs;
    const day = Math.floor(withinMonth / dayMs) + 1;
    const withinDay = withinMonth % dayMs;

    const skyblockSecond = Math.floor((withinDay / dayMs) * SKYBLOCK_SECONDS_PER_DAY);
    const hour24 = Math.floor(skyblockSecond / 3600) % 24;
    const minute = Math.floor((skyblockSecond % 3600) / 60);

    return {
        year,
        month,
        day,
        hour24,
        minute,
        monthName: SKYBLOCK_MONTH_NAMES[month - 1],
        season: seasonFromMonth(month),
        absoluteDayNumber: Math.floor(deltaMs / dayMs) + 1,
    };
}

function matchesWhen(day, month, range) {
    const startDay = range.start.day ?? day;
    const endDay = range.end.day ?? day;
    const startMonth = range.start.month ?? month;
    const endMonth = range.end.month ?? month;
    return day >= startDay && day <= endDay && month >= startMonth && month <= endMonth;
}

function isCalendarRuleVisible(ruleKey) {
    if (ruleKey === 'mythologicalRitual') {
        return Boolean(activePerks.value.mythological_ritual);
    }

    if (ruleKey === 'jerryWorkshop') {
        return Boolean(activePerks.value.jerry_workshop);
    }

    return true;
}

function getEventsForSkyDate(day, month, year) {
    const absoluteDayNumber = ((year - 1) * SKYBLOCK_DAYS_PER_YEAR) + ((month - 1) * SKYBLOCK_DAYS_PER_MONTH) + day;
    const list = [];

    for (const rule of SKYBLOCK_EVENT_RULES) {
        if (!isCalendarRuleVisible(rule.key)) {
            continue;
        }

        if (rule.when.some((range) => matchesWhen(day, month, range))) {
            list.push({ key: rule.key, name: rule.name });
        }
    }

    if (absoluteDayNumber % 3 === 0) {
        list.push({ key: 'dark_auction', name: 'Dark Auction' });
    }

    if (absoluteDayNumber % 3 === 1) {
        list.push({ key: 'jacob', name: "Jacob's Event" });
    }

    const kingIndex = (5 + absoluteDayNumber) % DWARVEN_KINGS.length;
    list.push({ key: 'dwarven_king', name: `King ${DWARVEN_KINGS[kingIndex]}` });

    return list;
}

function shiftSkyDateByDays(day, month, year, deltaDays) {
    let d = day;
    let m = month;
    let y = year;
    let steps = deltaDays;

    while (steps > 0) {
        d += 1;
        if (d > SKYBLOCK_DAYS_PER_MONTH) {
            d = 1;
            m += 1;
            if (m > SKYBLOCK_MONTHS_PER_YEAR) {
                m = 1;
                y += 1;
            }
        }
        steps -= 1;
    }

    while (steps < 0) {
        d -= 1;
        if (d < 1) {
            d = SKYBLOCK_DAYS_PER_MONTH;
            m -= 1;
            if (m < 1) {
                m = SKYBLOCK_MONTHS_PER_YEAR;
                y -= 1;
            }
        }
        steps += 1;
    }

    return { day: d, month: m, year: y };
}

function pickPrimaryEvent(eventsForDay) {
    const priority = [
        'newYear',
        'winter',
        'jerryWorkshop',
        'mythologicalRitual',
        'spookyFestival',
        'zoo',
        'electionOver',
        'cotfs',
        'dark_auction',
        'jacob',
        'interest',
        'dwarven_king',
    ];

    for (const key of priority) {
        const found = eventsForDay.find((e) => e.key === key);
        if (found) return found;
    }

    return eventsForDay[0] ?? null;
}

const currentSkyblockDate = computed(() => getSkyblockDateFromMs(nowMs.value));

const currentSkyblockMonthAbsolute = computed(() => {
    return ((currentSkyblockDate.value.year - 1) * SKYBLOCK_MONTHS_PER_YEAR) + (currentSkyblockDate.value.month - 1);
});

const currentSkyblockAbsoluteDay = computed(() => {
    return currentSkyblockDate.value.absoluteDayNumber;
});

const currentSkyblockDay = computed(() => {
    return currentSkyblockDate.value.day;
});

const displayedMonthAbsolute = computed(() => {
    return currentSkyblockMonthAbsolute.value + displayedMonthOffset.value;
});

const displayedCalendarYear = computed(() => {
    return Math.floor(displayedMonthAbsolute.value / SKYBLOCK_MONTHS_PER_YEAR) + 1;
});

const displayedCalendarMonth = computed(() => {
    return ((displayedMonthAbsolute.value % SKYBLOCK_MONTHS_PER_YEAR) + SKYBLOCK_MONTHS_PER_YEAR) % SKYBLOCK_MONTHS_PER_YEAR + 1;
});

const currentSkyblockTimeLabel = computed(() => {
    const date = currentSkyblockDate.value;
    const hour12 = date.hour24 % 12 === 0 ? 12 : (date.hour24 % 12);
    const period = date.hour24 >= 12 ? 'pm' : 'am';
    return `${String(hour12).padStart(2, '0')}:${String(date.minute).padStart(2, '0')}${period} ${date.day}/${date.month}/${date.year} ${date.season}`;
});

const calendarTrackLabel = computed(() => {
    const selectedInDisplayedMonth = selectedCalendarMonthAbsolute.value === displayedMonthAbsolute.value;
    const trackedDay = selectedInDisplayedMonth ? selectedCalendarDay.value : currentSkyblockDay.value;
    const trackedMode = selectedInDisplayedMonth ? 'Selected' : 'Today';
    const monthName = SKYBLOCK_MONTH_NAMES[displayedCalendarMonth.value - 1];
    return `${trackedMode} Day ${trackedDay}, ${monthName}, Year ${displayedCalendarYear.value}`;
});

const calendarSlots = computed(() => {
    const slots = Array.from({ length: 54 }, () => ({ item: null, day: null, special: null }));
    const month = displayedCalendarMonth.value;
    const year = displayedCalendarYear.value;

    for (let day = 1; day <= SKYBLOCK_DAYS_PER_MONTH; day++) {
        const zero = day - 1;
        const row = Math.floor(zero / 7) + 1;
        const col = (zero % 7) + 1;
        const index = row * 9 + col;
        const shiftedDate = shiftSkyDateByDays(day, month, year, SKYBLOCK_CALENDAR_EVENT_DAY_OFFSET);
        const eventsForDay = getEventsForSkyDate(shiftedDate.day, shiftedDate.month, shiftedDate.year);
        const primary = pickPrimaryEvent(eventsForDay);
        const override = primary ? EVENT_ITEM_OVERRIDES[primary.key] : null;

        const item = {
            name: primary ? primary.name : `SkyBlock Day ${day}`,
            count: 1,
            rarity: override?.rarity ?? 'COMMON',
            skyblock_id: override?.skyblock_id ?? null,
            texture_path: override?.texture_path ?? '/item/map_empty',
            lore_html: [
                `Date: ${day}/${month}/${year}`,
                eventsForDay.length > 0 ? eventsForDay.map((e) => e.name).join(', ') : 'No major event scheduled',
                'Daily market and profile activity',
            ],
        };

        slots[index] = {
            item,
            day,
            special: primary,
        };
    }

    return slots;
});

function formatDuration(totalSeconds) {
    const sec = Math.max(0, Math.floor(totalSeconds));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    if (h > 0) {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function formatRingDuration(totalSeconds) {
    const sec = Math.max(0, Math.floor(totalSeconds));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    // Keep ring text compact so it always fits the small circular timer.
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}`;
    }

    return `${m}:${String(s).padStart(2, '0')}`;
}

function formatCompactDuration(totalSeconds) {
    const sec = Math.max(0, Math.floor(totalSeconds));
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);

    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatClock(unixSeconds) {
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: localTz.value,
    }).format(new Date(unixSeconds * 1000));
}

function strokeOffset(progress) {
    const clamped = Math.max(0, Math.min(1, progress));
    const circumference = 2 * Math.PI * 52;
    return circumference * (1 - clamped);
}

function isTimerExpanded(eventKey) {
    return expandedTimer.value === eventKey;
}

function toggleTimerExpanded(eventKey) {
    expandedTimer.value = expandedTimer.value === eventKey ? null : eventKey;
}

function onDetailBeforeEnter(el) {
    el.style.height = '0';
    el.style.opacity = '0';
    el.style.transform = 'translateY(-8px) scale(0.98)';
}

function onDetailEnter(el, done) {
    const targetHeight = `${el.scrollHeight}px`;
    el.style.transition = 'height 320ms cubic-bezier(0.2, 0.9, 0.18, 1.05), opacity 220ms ease, transform 320ms cubic-bezier(0.2, 0.9, 0.18, 1.05)';

    requestAnimationFrame(() => {
        el.style.height = targetHeight;
        el.style.opacity = '1';
        el.style.transform = 'translateY(0) scale(1)';
    });

    const onEnd = (evt) => {
        if (evt.propertyName !== 'height') return;
        el.style.height = 'auto';
        el.style.transition = '';
        el.removeEventListener('transitionend', onEnd);
        done();
    };

    el.addEventListener('transitionend', onEnd);
}

function onDetailBeforeLeave(el) {
    el.style.height = `${el.scrollHeight}px`;
    el.style.opacity = '1';
    el.style.transform = 'translateY(0) scale(1)';
}

function onDetailLeave(el, done) {
    void el.offsetHeight;
    el.style.transition = 'height 240ms cubic-bezier(0.4, 0, 0.2, 1), opacity 180ms ease, transform 220ms ease';

    requestAnimationFrame(() => {
        el.style.height = '0';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-6px) scale(0.98)';
    });

    const onEnd = (evt) => {
        if (evt.propertyName !== 'height') return;
        el.style.transition = '';
        el.removeEventListener('transitionend', onEnd);
        done();
    };

    el.addEventListener('transitionend', onEnd);
}

function selectCalendarDay(day) {
    if (!day) return;
    selectedCalendarDay.value = day;
    selectedCalendarMonthAbsolute.value = displayedMonthAbsolute.value;
}

function selectPreviousMonth() {
    displayedMonthOffset.value -= 1;
}

function selectNextMonth() {
    displayedMonthOffset.value += 1;
}

function jumpToCurrentMonth() {
    displayedMonthOffset.value = 0;
    selectedCalendarDay.value = currentSkyblockDay.value;
    selectedCalendarMonthAbsolute.value = currentSkyblockMonthAbsolute.value;
}

function setSelectedFromAbsoluteDay(absoluteDay) {
    const monthAbsolute = Math.floor(absoluteDay / SKYBLOCK_DAYS_PER_MONTH);
    const day = ((absoluteDay % SKYBLOCK_DAYS_PER_MONTH) + SKYBLOCK_DAYS_PER_MONTH) % SKYBLOCK_DAYS_PER_MONTH + 1;

    selectedCalendarMonthAbsolute.value = monthAbsolute;
    selectedCalendarDay.value = day;
    displayedMonthOffset.value = monthAbsolute - currentSkyblockMonthAbsolute.value;
}

function selectPreviousDay() {
    const currentAbsolute = (selectedCalendarMonthAbsolute.value * SKYBLOCK_DAYS_PER_MONTH) + (selectedCalendarDay.value - 1);
    setSelectedFromAbsoluteDay(currentAbsolute - 1);
}

function selectNextDay() {
    const currentAbsolute = (selectedCalendarMonthAbsolute.value * SKYBLOCK_DAYS_PER_MONTH) + (selectedCalendarDay.value - 1);
    setSelectedFromAbsoluteDay(currentAbsolute + 1);
}

function loadNotifyPrefs() {
    try {
        const raw = localStorage.getItem(NOTIFY_PREFS_KEY);
        const keys = raw ? JSON.parse(raw) : [];
        for (const event of events) {
            notifyEnabled[event.key] = Array.isArray(keys) ? keys.includes(event.key) : false;
        }
        notifyEnabled.election_cycle = Array.isArray(keys) ? keys.includes('election_cycle') : false;
    } catch {
        for (const event of events) {
            notifyEnabled[event.key] = false;
        }
        notifyEnabled.election_cycle = false;
    }
}

function saveNotifyPrefs() {
    const enabledKeys = events.filter((e) => notifyEnabled[e.key]).map((e) => e.key);
    if (notifyEnabled.election_cycle) {
        enabledKeys.push('election_cycle');
    }
    try {
        localStorage.setItem(NOTIFY_PREFS_KEY, JSON.stringify(enabledKeys));
    } catch { /* quota / private-browsing */ }
}

function loadSentNotificationKeys() {
    try {
        const raw = localStorage.getItem(NOTIFY_SENT_KEY);
        const keys = raw ? JSON.parse(raw) : [];
        sentKeys.value = new Set(Array.isArray(keys) ? keys : []);
    } catch {
        sentKeys.value = new Set();
    }
}

function saveSentNotificationKeys() {
    localStorage.setItem(NOTIFY_SENT_KEY, JSON.stringify([...sentKeys.value].slice(-200)));
}

function buildSentKey(eventKey, startUnix) {
    return `${eventKey}:${startUnix}`;
}

async function registerEventTimerSw() {
    if (!('serviceWorker' in navigator)) return;

    try {
        swRegistration.value = await navigator.serviceWorker.register('/event-timer-sw.js', {
            scope: '/',
        });
    } catch {
        swRegistration.value = null;
    }
}

async function requestNotificationPermission() {
    if (typeof Notification === 'undefined') {
        notificationPermission.value = 'unsupported';
        return false;
    }

    if (Notification.permission === 'granted') {
        notificationPermission.value = 'granted';
        return true;
    }

    if (Notification.permission === 'denied') {
        notificationPermission.value = 'denied';
        return false;
    }

    const result = await Notification.requestPermission();
    notificationPermission.value = result;
    return result === 'granted';
}

async function scheduleTimestampTrigger(eventCard) {
    if (!swRegistration.value || !supportsTimestampTrigger.value) return;

    const fireAt = (eventCard.nextStart - NOTIFY_LEAD_SECONDS) * 1000;
    if (fireAt <= Date.now()) return;

    const trigger = new window.TimestampTrigger(fireAt);
    await swRegistration.value.showNotification(`${eventCard.name} starts soon`, {
        body: `${eventCard.name} starts in 5 minutes.`,
        tag: `event-timer-${eventCard.key}`,
        icon: getNotificationIcon(eventCard.key),
        badge: '/favicon.webp',
        renotify: false,
        timestamp: fireAt,
        showTrigger: trigger,
        data: {
            eventKey: eventCard.key,
            startUnix: eventCard.nextStart,
        },
    });
}

function getNotificationIcon(eventKey) {
    return NOTIFICATION_ICON_MAP[eventKey] || '/img/logo-white.webp';
}

async function notifyNow(title, body, tag, data = {}) {
    console.log('[EventTimer] notifyNow called:', { title, tag, swReg: !!swRegistration.value, permission: Notification?.permission });

    const icon = getNotificationIcon(data.eventKey || tag.replace('event-timer-confirm-', '').replace('event-timer-now-', ''));
    const badge = '/favicon.webp';

    // Prefer showing via SW registration (works even when tab is in background).
    const reg = swRegistration.value;
    if (reg) {
        try {
            await reg.showNotification(title, { body, tag, icon, badge, renotify: false, data });
            console.log('[EventTimer] SW showNotification success');
            return;
        } catch (err) {
            console.warn('[EventTimer] SW showNotification failed, falling back:', err);
        }
    }

    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        console.log('[EventTimer] Using fallback new Notification()');
        new Notification(title, { body, tag, icon, data });
    } else {
        console.warn('[EventTimer] Cannot show notification — no SW, no permission');
    }
}

async function toggleNotify(eventCard) {
    const enabled = !notifyEnabled[eventCard.key];
    console.log('[EventTimer] toggleNotify:', eventCard.key, 'enabling:', enabled);

    if (enabled) {
        const granted = await requestNotificationPermission();
        console.log('[EventTimer] Permission result:', granted, Notification?.permission);
        if (!granted) {
            notifyDeniedFlash.value = true;
            setTimeout(() => { notifyDeniedFlash.value = false; }, 4000);
            return;
        }
    }

    notifyEnabled[eventCard.key] = enabled;
    saveNotifyPrefs();

    if (enabled) {
        notifyConfirmKey.value = eventCard.key;
        setTimeout(() => { if (notifyConfirmKey.value === eventCard.key) notifyConfirmKey.value = null; }, 3000);

        // Immediate confirmation so the user knows it works.
        try {
            await notifyNow(
                `${eventCard.name} — notifications on`,
                `You'll be notified 5 minutes before ${eventCard.name} starts.`,
                `event-timer-confirm-${eventCard.key}`,
                { eventKey: eventCard.key },
            );
        } catch (err) {
            console.error('[EventTimer] Confirmation notification failed:', err);
        }

        try {
            await scheduleTimestampTrigger(eventCard);
        } catch {
            // Fallback timer loop handles unsupported trigger scheduling.
        }
    }
}

function runNotificationTick() {
    if (notificationPermission.value !== 'granted') return;

    const nowSec = Math.floor(Date.now() / 1000);

    for (const eventCard of timerCards.value) {
        if (!notifyEnabled[eventCard.key]) continue;

        const notifyUnix = eventCard.nextStart - NOTIFY_LEAD_SECONDS;
        const sentKey = buildSentKey(eventCard.key, eventCard.nextStart);

        // In-tab fallback notification when the lead window hits.
        if (nowSec >= notifyUnix && nowSec < notifyUnix + 60 && !sentKeys.value.has(sentKey)) {
            sentKeys.value.add(sentKey);
            saveSentNotificationKeys();

            notifyNow(
                `${eventCard.name} starts soon`,
                `${eventCard.name} starts in 5 minutes.`,
                `event-timer-now-${eventCard.key}`,
                { eventKey: eventCard.key, startUnix: eventCard.nextStart }
            );

            if (supportsTimestampTrigger.value) {
                scheduleTimestampTrigger(eventCard).catch(() => {});
            }
        }
    }
}

onMounted(() => {
    selectedCalendarDay.value = currentSkyblockDay.value;
    selectedCalendarMonthAbsolute.value = currentSkyblockMonthAbsolute.value;

    loadNotifyPrefs();
    loadSentNotificationKeys();
    registerEventTimerSw();
    preloadAllTextures().then(() => {
        textureVersion.value++;
    });

    timerId = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);

    notifyTickId = setInterval(() => {
        runNotificationTick();
    }, 15000);

    // Catch up on missed notification windows when the tab regains focus.
    document.addEventListener('visibilitychange', onVisibilityChange);
});

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        nowMs.value = Date.now();
        runNotificationTick();
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);

    if (timerId !== null) {
        clearInterval(timerId);
        timerId = null;
    }

    if (notifyTickId !== null) {
        clearInterval(notifyTickId);
        notifyTickId = null;
    }
});
</script>

<template>
    <Head :title="t('eventTimer.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 text-center text-2xl font-bold leading-tight text-white sm:text-3xl">{{ currentSkyblockTimeLabel }}</div>

            <TransitionGroup
                name="timer-card"
                tag="div"
                class="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <section
                    v-for="event in timerCards"
                    :key="event.key"
                    class="lb-surface w-full max-w-md self-start overflow-hidden border bg-surface-900/75 backdrop-blur-sm transition sm:max-w-none"
                    :class="[
                        event.boosted
                            ? 'border-cyan-500/40 event-card-boosted'
                            : event.isActive
                              ? 'border-green-500/60 shadow-[0_16px_40px_rgba(0,0,0,0.35),0_0_24px_rgba(34,197,94,0.16)]'
                              : 'border-border/80 shadow-[0_16px_40px_rgba(0,0,0,0.35)]',
                        isTimerExpanded(event.key) ? 'timer-card-expanded' : 'timer-card-collapsed',
                    ]"
                >
                    <button
                        type="button"
                        class="w-full px-2.5 py-2.5 text-left transition hover:bg-surface-800/45"
                        @click="toggleTimerExpanded(event.key)"
                    >
                        <div class="timer-card-inline">
                            <div class="timer-ring-shell">
                                <svg viewBox="0 0 128 128" class="h-full w-full -rotate-90">
                                    <circle cx="64" cy="64" r="52" stroke="rgba(148, 163, 184, 0.2)" stroke-width="10" fill="none" />
                                    <circle
                                        cx="64"
                                        cy="64"
                                        r="52"
                                        stroke-width="10"
                                        fill="none"
                                        stroke-linecap="round"
                                        :stroke="event.isActive ? '#22c55e' : '#f59e0b'"
                                        :stroke-dasharray="2 * Math.PI * 52"
                                        :stroke-dashoffset="strokeOffset(event.progress)"
                                    />
                                </svg>
                                <div class="timer-ring-content">
                                    <div class="timer-time">{{ formatRingDuration(event.stateRemaining) }}</div>
                                </div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1.5">
                                    <h3 class="truncate text-[13px] font-semibold text-white">{{ event.name }}</h3>
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide"
                                            :class="event.isActive ? 'bg-green-500/15 text-green-300' : 'bg-yellow-500/15 text-yellow-300'"
                                        >
                                            {{ event.isActive ? t('eventTimer.live') : t('eventTimer.upcoming') }}
                                        </span>
                                        <span
                                            v-if="event.boosted"
                                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[8px] font-semibold uppercase tracking-wide text-cyan-200 animate-pulse"
                                        >
                                            {{ t('eventTimer.specialPerk') }}
                                        </span>
                                        <span
                                            v-if="event.dungeonRelated && activePerks.dungeon_benefit"
                                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[8px] font-semibold uppercase tracking-wide text-cyan-200 animate-pulse"
                                        >
                                            {{ t('eventTimer.benefitActive') }}
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-1 text-[11px] font-medium leading-tight text-neutral">
                                    {{ event.isActive ? event.activeLabel : event.upcomingLabel }}
                                </p>

                                <div class="mt-2 text-[11px] text-neutral">
                                    {{ t('eventTimer.next') }} <span class="font-semibold text-white/90">{{ formatClock(event.nextStart) }}</span>
                                </div>

                                <div class="mt-1 text-[10px] text-neutral/90">
                                    {{ isTimerExpanded(event.key) ? t('eventTimer.hideDetails') : t('eventTimer.showDetails') }}
                                </div>
                            </div>
                        </div>
                    </button>

                    <Transition
                        @before-enter="onDetailBeforeEnter"
                        @enter="onDetailEnter"
                        @before-leave="onDetailBeforeLeave"
                        @leave="onDetailLeave"
                    >
                        <div v-if="isTimerExpanded(event.key)" class="timer-detail-panel border-t border-border/70 bg-surface-900/40 px-4 py-3 backdrop-blur-sm">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <p class="text-xs text-neutral">
                                        {{ t('eventTimer.nextStart') }} <span class="text-white">{{ formatClock(event.nextStart) }}</span>
                                        <span class="mx-2 text-neutral/60">•</span>
                                        {{ t('eventTimer.nextEnd') }} <span class="text-white">{{ formatClock(event.nextEnd) }}</span>
                                    </p>

                                    <div class="mt-2 lb-surface border border-border/80 bg-surface-800/40 px-3 py-2 backdrop-blur-sm">
                                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral">{{ t('eventTimer.nextOccurrences') }}</div>
                                        <div class="space-y-1 text-xs text-neutral">
                                            <div
                                                v-for="occ in event.nextOccurrences"
                                                :key="occ.startUnix"
                                                class="flex items-center justify-between"
                                            >
                                                <span>{{ t('eventTimer.in') }} <span class="text-white">{{ formatCompactDuration(occ.inSeconds) }}</span></span>
                                                <span class="text-white/80">{{ formatClock(occ.startUnix) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    class="rounded-md border px-2.5 py-1.5 text-[11px] font-semibold transition"
                                    :class="event.notifyEnabled
                                        ? 'border-green-400/50 bg-green-500/10 text-green-300'
                                        : 'border-border bg-surface-700 text-neutral hover:text-white'"
                                    @click.stop="toggleNotify(event)"
                                >
                                    {{ event.notifyEnabled ? t('eventTimer.notifyOn') : t('eventTimer.notifyMe') }}
                                </button>

                                <p v-if="notifyConfirmKey === event.key" class="mt-1.5 text-[10px] font-medium text-green-400 transition-opacity">
                                    {{ t('eventTimer.notifyConfirm') }}
                                </p>
                                <p v-else-if="notifyDeniedFlash" class="mt-1 text-[10px] text-red-400">
                                    {{ t('eventTimer.notifyBlocked') }}
                                </p>
                            </div>
                        </div>
                    </Transition>
                </section>
            </TransitionGroup>

            <div class="mt-8">
                <div class="mb-3 text-center text-2xl font-semibold text-white/90">{{ calendarTrackLabel }}</div>

                <div class="mx-auto flex w-fit items-center justify-center gap-3 sm:gap-4">
                    <button
                        type="button"
                        class="calendar-side-arrow"
                        aria-label="Previous month"
                        @click="selectPreviousMonth"
                    >
                        <span aria-hidden="true">&lt;</span>
                    </button>

                    <div class="min-w-0 overflow-x-auto">
                        <div class="inventory-panel">
                            <div class="inventory-grid">
                                <button
                                    v-for="(slot, idx) in calendarSlots"
                                    :key="idx"
                                    type="button"
                                    class="calendar-slot-btn"
                                    :class="{
                                        'calendar-slot-selected': slot.day && slot.day === selectedCalendarDay && selectedCalendarMonthAbsolute === displayedMonthAbsolute,
                                        'calendar-slot-today': slot.day && slot.day === currentSkyblockDay && displayedMonthAbsolute === currentSkyblockMonthAbsolute,
                                        'calendar-slot-today-selected': slot.day && slot.day === currentSkyblockDay && selectedCalendarDay === slot.day && displayedMonthAbsolute === currentSkyblockMonthAbsolute && selectedCalendarMonthAbsolute === displayedMonthAbsolute,
                                    }"
                                    @click="selectCalendarDay(slot.day)"
                                >
                                    <ItemSlot :item="slot.item" />
                                    <span v-if="slot.day" class="calendar-day-badge">{{ slot.day }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="calendar-side-arrow"
                        aria-label="Next month"
                        @click="selectNextMonth"
                    >
                        <span aria-hidden="true">&gt;</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.timer-card-inline {
    display: flex;
    align-items: center;
    gap: 10px;
}

.event-card-boosted {
    box-shadow:
        0 16px 40px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(34, 211, 238, 0.38),
        0 0 28px rgba(34, 211, 238, 0.14);
}

.timer-card-collapsed,
.timer-card-expanded {
    transition: transform 240ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 240ms ease, filter 240ms ease;
    transform-origin: center top;
}

.timer-card-collapsed {
    transform: scale(1);
}

.timer-card-expanded {
    transform: translateY(-2px) scale(1.015);
    filter: saturate(1.04);
}

.timer-card-move {
    transition: transform 340ms cubic-bezier(0.22, 0.9, 0.24, 1);
}

.timer-card-enter-active {
    transition: opacity 220ms ease, transform 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.timer-card-leave-active {
    transition: opacity 160ms ease, transform 180ms ease;
}

.timer-card-enter-from,
.timer-card-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.985);
}

.timer-detail-panel {
    overflow: hidden;
    transform-origin: top center;
    will-change: height, opacity, transform;
}

.timer-ring-shell {
    position: relative;
    flex-shrink: 0;
    height: 72px;
    width: 72px;
}

.timer-ring-content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    letter-spacing: 0.01em;
    line-height: 1;
    padding: 0 4px;
}

.timer-time {
    font-weight: 700;
    color: rgb(255 255 255);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
    line-height: 1;
    font-size: 12px;
}

.calendar-slot-btn {
    all: unset;
    position: relative;
    display: block;
    cursor: pointer;
    border-radius: 6px;
    transition: transform 140ms ease, filter 140ms ease;
}

.calendar-slot-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.04);
}

.calendar-slot-btn:focus-visible {
    outline: 2px solid rgba(255, 255, 255, 0.7);
    outline-offset: 1px;
}

.calendar-slot-selected {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.58);
}

.calendar-slot-today {
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.82), 0 0 12px rgba(34, 197, 94, 0.35);
}

.calendar-slot-today-selected {
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.96), 0 0 14px rgba(34, 197, 94, 0.42);
}

.calendar-day-badge {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 10px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.85);
    pointer-events: none;
}

.calendar-side-arrow {
    display: inline-flex;
    height: 34px;
    width: 34px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: transparent;
    color: rgba(255, 255, 255, 0.88);
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
    transition: color 120ms ease, border-color 120ms ease, transform 120ms ease;
}

.calendar-side-arrow:hover {
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.55);
    transform: translateY(-1px);
}

.calendar-side-arrow:focus-visible {
    outline: 2px solid rgba(255, 255, 255, 0.75);
    outline-offset: 2px;
}

/* Match Leaderboards row / search panel surface (squircle where supported). */
.lb-surface {
    border-radius: 1.125rem;
}

@supports (corner-shape: squircle) {
    .lb-surface {
        corner-shape: squircle;
    }
}

</style>
