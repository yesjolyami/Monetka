<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, CameraOff } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    decodeQrFromFile,
    decodeQrFromVideo,
    grabViewfinderJpeg,
    looksLikeFiscalQr,
} from '@/lib/scanReceiptQr';

type ReceiptItem = {
    name: string;
    quantity: string;
    price: number;
    sum: number;
    suggested_category_id?: number | null;
};

type Receipt = {
    qr: string;
    fiscal: {
        t: string;
        s: string;
        fn: string;
        i: string;
        fp: string;
        n: string;
    };
    seller: string | null;
    inn: string | null;
    datetime: string | null;
    occurred_on?: string;
    total: number | null;
    items: ReceiptItem[];
};

type AccountOption = { id: number; name: string };
type ExpenseCategory = { id: number; name: string; emoji: string; color: string };
type ReceiptLine = {
    include: boolean;
    name: string;
    quantity: string;
    sum: number;
    category_id: number;
};

const props = defineProps<{
    receipt: Receipt | null;
    connected: boolean;
    fnsInn: string | null;
    accounts: AccountOption[];
    expenseCategories: ExpenseCategory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Чек ФНС',
                href: '/receipts',
            },
        ],
    },
});

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

const videoRef = useTemplateRef<HTMLVideoElement>('preview');
const qrInputRef = useTemplateRef<HTMLTextAreaElement>('qr');
const photoInputRef = useTemplateRef<HTMLInputElement>('photo');

const cameraOn = ref(false);
const cameraError = ref<string | null>(null);
const cameraBusy = ref(false);
const scanHint = ref<string | null>(null);
const lines = ref<ReceiptLine[]>([]);
const accountId = ref<number | ''>('');
const occurredOn = ref('');
const changingCabinet = ref(false);
const cabinet = ref<'missing' | 'checking' | 'ok' | 'invalid'>(
    props.connected ? 'checking' : 'missing',
);

const scannerAllowed = computed(
    () => cabinet.value === 'checking' || cabinet.value === 'ok',
);
const showConnectForm = computed(
    () =>
        cabinet.value === 'missing' ||
        cabinet.value === 'invalid' ||
        changingCabinet.value,
);

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

const includedLines = computed(() => lines.value.filter((line) => line.include));
const includedTotal = computed(() =>
    includedLines.value.reduce((total, line) => total + line.sum, 0),
);

function fallbackCategoryId(): number {
    return (
        props.expenseCategories.find((category) => category.name === 'Продукты')?.id ??
        props.expenseCategories[0]?.id ??
        0
    );
}

function syncReceiptForm(): void {
    const current = props.receipt;

    if (!current) {
        lines.value = [];

        return;
    }

    occurredOn.value = current.occurred_on ?? localDateString();
    const fallback = fallbackCategoryId();
    lines.value = current.items.map((item) => ({
        include: true,
        name: item.name,
        quantity: item.quantity,
        sum: item.sum,
        category_id: item.suggested_category_id ?? fallback,
    }));

    if (props.accounts[0] && accountId.value === '') {
        accountId.value = props.accounts[0].id;
    }
}

function localDateString(date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

watch(() => props.receipt, syncReceiptForm, { immediate: true });
watch(
    () => props.accounts,
    (list) => {
        if (list[0] && accountId.value === '') {
            accountId.value = list[0].id;
        }
    },
    { immediate: true },
);
watch(
    () => props.connected,
    (connected, wasConnected) => {
        if (connected && wasConnected === false) {
            cabinet.value = 'ok';
            changingCabinet.value = false;
        }

        if (! connected) {
            cabinet.value = 'missing';
        }
    },
);

let stream: MediaStream | null = null;
let scanning = false;

function formatAmount(minor: number): string {
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${minor < 0 ? '−' : ''}${whole}.${cents} ${currency.value}`;
}

function applyQr(raw: string): void {
    const qrInput = qrInputRef.value;

    if (qrInput) {
        qrInput.value = raw;
    }

    const photoInput = photoInputRef.value;

    if (photoInput) {
        photoInput.value = '';
    }
}

function stopCamera(): void {
    scanning = false;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;

    const video = videoRef.value;

    if (video) {
        video.srcObject = null;
    }

    cameraOn.value = false;
}

async function submitViewfinderPhoto(video: HTMLVideoElement): Promise<void> {
    const photoInput = photoInputRef.value;
    const blob = await grabViewfinderJpeg(video);

    if (!photoInput || !blob) {
        scanHint.value = 'Не удалось снять кадр. Держите QR в рамке.';

        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(new File([blob], 'receipt-qr.jpg', { type: 'image/jpeg' }));
    photoInput.files = transfer.files;

    const qrInput = qrInputRef.value;

    if (qrInput) {
        qrInput.value = '';
    }

    submitForm();
}

async function scanLoop(): Promise<void> {
    let misses = 0;

    while (scanning && cameraOn.value && !cameraBusy.value) {
        const video = videoRef.value;

        if (video && video.videoWidth > 0) {
            const hit = await decodeQrFromVideo(video);

            if (hit) {
                applyQr(hit);
                submitForm();

                return;
            }

            misses += 1;
            scanHint.value = 'Держите QR в рамке — снимок уйдёт сам.';

            if (misses >= 6) {
                await submitViewfinderPhoto(video);

                return;
            }
        }

        await new Promise((resolve) => window.setTimeout(resolve, 200));
    }
}

async function openCamera(): Promise<void> {
    cameraError.value = null;
    scanHint.value = null;

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = window.isSecureContext
            ? 'Этот браузер не отдаёт камеру странице.'
            : 'Браузер отдаёт камеру только по HTTPS. Откройте https://monetka.local — по http:// запрос к камере блокируется.';

        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });
    } catch {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        } catch (error) {
            cameraError.value =
                error instanceof DOMException && error.name === 'NotAllowedError'
                    ? 'Доступ к камере запрещён. Разрешите её для этого сайта в настройках браузера.'
                    : 'Не удалось открыть камеру.';

            return;
        }
    }

    cameraOn.value = true;
    await nextTick();

    const video = videoRef.value;

    if (!video || !stream) {
        stopCamera();

        return;
    }

    video.srcObject = stream;
    await video.play();
    scanning = true;
    scanHint.value = 'Держите QR в рамке — снимок уйдёт сам.';
    void scanLoop();
}

async function captureFrame(): Promise<void> {
    const video = videoRef.value;

    if (!video || video.videoWidth === 0 || cameraBusy.value) {
        return;
    }

    const hit = await decodeQrFromVideo(video);

    if (hit) {
        applyQr(hit);
        submitForm();

        return;
    }

    await submitViewfinderPhoto(video);
}

async function onPhotoChange(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file || cameraBusy.value) {
        return;
    }

    scanHint.value = 'Читаю QR с фото…';

    try {
        const hit = await decodeQrFromFile(file);

        if (!hit) {
            scanHint.value = 'На фото QR сразу не нашёлся — отправим снимок на сервер.';

            return;
        }

        applyQr(hit);

        if (looksLikeFiscalQr(hit)) {
            scanHint.value = null;
            submitForm();

            return;
        }

        scanHint.value =
            'Прочитан QR, но он не фискальный. Нужен код внизу чека (после суммы).';
    } catch {
        scanHint.value = 'Не удалось разобрать файл. Можно всё равно отправить на сервер.';
    }
}

function submitForm(): void {
    if (cameraBusy.value || !scannerAllowed.value) {
        return;
    }

    cameraBusy.value = true;
    stopCamera();
    photoInputRef.value?.form?.requestSubmit();
}

function onCabinetConnected(): void {
    cabinet.value = 'ok';
    changingCabinet.value = false;
}

async function verifyCabinet(): Promise<void> {
    cabinet.value = 'checking';

    try {
        const response = await fetch('/receipts/status', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            cabinet.value = 'invalid';
            stopCamera();

            return;
        }

        const data = (await response.json()) as { connected?: boolean; ok?: boolean };

        if (data.ok) {
            cabinet.value = 'ok';
            changingCabinet.value = false;

            return;
        }

        cabinet.value = data.connected ? 'invalid' : 'missing';
        stopCamera();
    } catch {
        cabinet.value = 'invalid';
        stopCamera();
    }
}

onMounted(() => {
    router.on('finish', () => {
        cameraBusy.value = false;
    });

    if (props.connected) {
        void verifyCabinet();
    }
});

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <Head title="Чек ФНС" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl">Разбор кассового чека</h1>
            <p class="text-muted-foreground text-sm">
                Сначала вход в личный кабинет nalog.ru — тем же ИНН и паролем,
                что в приложении «Проверка чеков». Потом можно сканировать QR.
            </p>
            <p
                v-if="cabinet === 'checking'"
                class="text-muted-foreground text-sm"
            >
                Проверяем вход в кабинет ФНС…
            </p>
            <p
                v-else-if="cabinet === 'ok'"
                class="text-muted-foreground text-sm"
            >
                Кабинет ФНС доступен
                <span v-if="fnsInn">(ИНН {{ fnsInn }})</span>.
                <button
                    type="button"
                    class="text-foreground underline"
                    @click="changingCabinet = !changingCabinet"
                >
                    {{ changingCabinet ? 'Скрыть форму' : 'Изменить данные' }}
                </button>
            </p>
            <p
                v-else-if="cabinet === 'invalid'"
                class="text-sm text-amber-600 dark:text-amber-400"
            >
                Не удалось войти в кабинет ФНС. Возможно, сменился пароль —
                введите данные снова.
            </p>
            <p v-if="scannerAllowed" class="text-muted-foreground text-sm">
                Включите камеру и подержите фискальный QR в рамке — кадр
                уйдёт сам. На чеке это обычно код внизу, после суммы.
            </p>
        </div>

        <Form
            v-if="showConnectForm"
            method="post"
            action="/receipts/connect"
            class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            v-slot="{ errors, processing }"
            @success="onCabinetConnected"
        >
            <div class="flex flex-col gap-2">
                <Label for="inn">ИНН</Label>
                <Input
                    id="inn"
                    name="inn"
                    type="text"
                    inputmode="numeric"
                    autocomplete="username"
                    required
                    :value="fnsInn ?? ''"
                    placeholder="10 или 12 цифр"
                />
                <InputError :message="errors.inn" />
            </div>
            <div class="flex flex-col gap-2">
                <Label for="fns-password">Пароль кабинета nalog.ru</Label>
                <PasswordInput
                    id="fns-password"
                    name="password"
                    autocomplete="off"
                    required
                />
                <InputError :message="errors.password" />
            </div>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Проверить вход
            </Button>
        </Form>

        <Form
            v-if="scannerAllowed"
            method="post"
            action="/receipts"
            enctype="multipart/form-data"
            class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            v-slot="{ errors, processing }"
        >
            <div class="flex flex-col gap-2">
                <Label>Камера</Label>
                <div
                    v-if="cameraOn"
                    class="relative overflow-hidden rounded-lg bg-black"
                >
                    <video
                        ref="preview"
                        class="block w-full bg-black"
                        autoplay
                        muted
                        playsinline
                    />
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div
                            class="aspect-square h-[72%] rounded-lg border-2 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                        />
                    </div>
                    <p
                        class="pointer-events-none absolute inset-x-0 bottom-0 bg-black/50 px-3 py-2 text-center text-xs text-white"
                    >
                        Держите QR в рамке — снимок уйдёт сам.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!cameraOn"
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="openCamera"
                    >
                        <Camera />
                        Включить камеру
                    </Button>
                    <template v-else>
                        <Button
                            type="button"
                            :disabled="processing || cameraBusy"
                            @click="captureFrame"
                        >
                            Снять кадр
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="stopCamera"
                        >
                            <CameraOff />
                            Выключить
                        </Button>
                    </template>
                </div>
                <p v-if="scanHint" class="text-muted-foreground text-sm">{{ scanHint }}</p>
                <p v-if="cameraError" class="text-destructive text-sm">{{ cameraError }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <Label for="qr">Строка из QR</Label>
                <textarea
                    id="qr"
                    ref="qr"
                    name="qr"
                    rows="3"
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 font-mono text-sm focus-visible:ring-2 focus-visible:outline-none"
                    placeholder="t=20240115T1830&s=128.50&fn=...&i=...&fp=...&n=1"
                />
                <InputError :message="errors.qr" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="photo">Или файл с диска</Label>
                <input
                    id="photo"
                    ref="photo"
                    name="photo"
                    type="file"
                    accept="image/*"
                    class="border-input file:text-foreground dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm file:me-3 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                    @change="onPhotoChange"
                />
                <InputError :message="errors.photo" />
            </div>

            <Button type="submit" :disabled="processing || cameraBusy">
                <Spinner v-if="processing || cameraBusy" />
                Запросить чек в ФНС
            </Button>
        </Form>

        <section v-if="receipt" class="bg-card flex flex-col gap-4 rounded-xl border p-4">
            <div>
                <h2 class="font-medium">{{ receipt.seller ?? 'Чек' }}</h2>
                <p class="text-muted-foreground text-sm">
                    <span v-if="receipt.inn">ИНН {{ receipt.inn }}. </span>
                    <span v-if="receipt.datetime">{{ receipt.datetime }}. </span>
                    ФН {{ receipt.fiscal.fn }}, ФД {{ receipt.fiscal.i }}, ФП
                    {{ receipt.fiscal.fp }}
                </p>
            </div>

            <p v-if="receipt.items.length === 0" class="text-muted-foreground text-sm">
                ФНС подтвердила реквизиты, но позиций в ответе нет.
            </p>

            <Form
                v-else
                method="post"
                action="/receipts/import"
                class="flex flex-col gap-4"
                v-slot="{ errors, processing }"
            >
                <p v-if="accounts.length === 0" class="text-sm text-amber-600 dark:text-amber-400">
                    Чтобы списать покупки, сначала
                    <Link href="/accounts" class="underline">создайте счёт</Link>.
                </p>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <Label for="account_id">С какого счёта списать</Label>
                        <select
                            id="account_id"
                            v-model.number="accountId"
                            name="account_id"
                            required
                            :class="selectClass"
                        >
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="errors.account_id" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="occurred_on">Дата</Label>
                        <Input
                            id="occurred_on"
                            v-model="occurredOn"
                            name="occurred_on"
                            type="date"
                            required
                        />
                        <InputError :message="errors.occurred_on" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium">Позиции</p>
                    <p class="text-muted-foreground text-sm">
                        Категория подставляется по названию, её можно сменить.
                        Снимите галочку, если позицию в журнал не нужно.
                    </p>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="(line, index) in lines"
                            :key="`${line.name}-${index}`"
                            class="grid gap-2 rounded-lg border p-3 sm:grid-cols-[auto_1fr_auto] sm:items-center"
                            :class="{ 'opacity-50': !line.include }"
                        >
                            <label class="flex items-start gap-2 text-sm">
                                <input
                                    v-model="line.include"
                                    type="checkbox"
                                    class="mt-1"
                                />
                                <span>
                                    {{ line.name }}
                                    <span class="text-muted-foreground">
                                        × {{ line.quantity }}
                                    </span>
                                </span>
                            </label>
                            <span class="font-medium tabular-nums sm:text-right">
                                {{ formatAmount(line.sum) }}
                            </span>
                            <select
                                v-model.number="line.category_id"
                                :disabled="!line.include"
                                :class="selectClass"
                                class="w-full sm:min-w-40"
                            >
                                <option
                                    v-for="category in expenseCategories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.emoji }} {{ category.name }}
                                </option>
                            </select>
                        </li>
                    </ul>
                    <InputError :message="errors.items" />
                </div>

                <template
                    v-for="(line, index) in includedLines"
                    :key="`payload-${index}`"
                >
                    <input type="hidden" :name="`items[${index}][name]`" :value="line.name" />
                    <input type="hidden" :name="`items[${index}][amount]`" :value="line.sum" />
                    <input
                        type="hidden"
                        :name="`items[${index}][category_id]`"
                        :value="line.category_id"
                    />
                </template>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm">
                        К записи:
                        <span class="font-medium tabular-nums">
                            {{ includedLines.length }} из {{ lines.length }},
                            {{ formatAmount(includedTotal) }}
                        </span>
                        <span v-if="receipt.total !== null" class="text-muted-foreground">
                            (в чеке {{ formatAmount(receipt.total) }})
                        </span>
                    </p>
                    <Button
                        type="submit"
                        :disabled="
                            processing ||
                            accounts.length === 0 ||
                            includedLines.length === 0
                        "
                    >
                        <Spinner v-if="processing" />
                        Записать в журнал
                    </Button>
                </div>
            </Form>
        </section>
    </div>
</template>
