<?php
/** Selector de mes (cuadrícula). Requiere $ticketMonthOptions; opcional $ticketMonthFilter, hidden, reset URL. */
if (!isset($ticketMonthOptions) || !is_array($ticketMonthOptions)) {
    $ticketMonthOptions = function_exists('listTicketMonthFilterOptions') ? listTicketMonthFilterOptions() : [];
}
$ticketMonthFilterHidden = isset($ticketMonthFilterHidden) && is_array($ticketMonthFilterHidden) ? $ticketMonthFilterHidden : [];
$ticketMonthFilterResetUrl = (string)($ticketMonthFilterResetUrl ?? '');
$selectedMonth = is_array($ticketMonthFilter ?? null) ? (string)($ticketMonthFilter['param'] ?? '') : '';
$selectedLabel = is_array($ticketMonthFilter ?? null) ? (string)($ticketMonthFilter['label'] ?? '') : '';

$monthShort = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$availableMap = [];
$yearsMap = [];
foreach ($ticketMonthOptions as $opt) {
    $ov = (string)($opt['value'] ?? '');
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ov)) {
        continue;
    }
    $availableMap[$ov] = (string)($opt['label'] ?? $ov);
    $yearsMap[(int)substr($ov, 0, 4)] = true;
}
$pickerYears = array_keys($yearsMap);
sort($pickerYears, SORT_NUMERIC);
if ($pickerYears === []) {
    $pickerYears = [(int)date('Y')];
}
$pickerInitialYear = (int)date('Y');
if ($selectedMonth !== '' && preg_match('/^(\d{4})-/', $selectedMonth, $sm)) {
    $pickerInitialYear = (int)$sm[1];
} else {
    $pickerInitialYear = (int)$pickerYears[count($pickerYears) - 1];
}
if (!in_array($pickerInitialYear, $pickerYears, true)) {
    $pickerInitialYear = (int)$pickerYears[count($pickerYears) - 1];
}

$triggerValue = $selectedLabel !== '' ? $selectedLabel : 'Todos los meses';

static $ticketMonthPickerAssetsLoaded = false;
if (!$ticketMonthPickerAssetsLoaded) {
    $ticketMonthPickerAssetsLoaded = true;
    $pickerCssPath = __DIR__ . '/../css/ticket-month-picker.css';
    $pickerJsPath = __DIR__ . '/../js/ticket-month-picker.js';
    $pickerCssV = is_file($pickerCssPath) ? (int)@filemtime($pickerCssPath) : 1;
    $pickerJsV = is_file($pickerJsPath) ? (int)@filemtime($pickerJsPath) : 1;
    echo '<link rel="stylesheet" href="css/ticket-month-picker.css?v=' . $pickerCssV . '">' . "\n";
    echo '<script src="js/ticket-month-picker.js?v=' . $pickerJsV . '" defer></script>' . "\n";
}
?>
<div class="ticket-month-picker"
     data-years="<?php echo html(json_encode(array_values($pickerYears), JSON_UNESCAPED_UNICODE)); ?>"
     data-available="<?php echo html(json_encode($availableMap, JSON_UNESCAPED_UNICODE)); ?>"
     data-initial-year="<?php echo (int)$pickerInitialYear; ?>"
     data-selected="<?php echo html($selectedMonth); ?>">
    <form method="get" action="tickets.php" class="ticket-month-picker__form">
        <?php foreach ($ticketMonthFilterHidden as $hName => $hVal): ?>
            <input type="hidden" name="<?php echo html((string)$hName); ?>" value="<?php echo html((string)$hVal); ?>">
        <?php endforeach; ?>
        <?php if (!empty($ticketMonthFilterResetPage)): ?>
            <input type="hidden" name="<?php echo html((string)$ticketMonthFilterResetPage); ?>" value="1">
        <?php endif; ?>
        <input type="hidden" name="month" value="<?php echo html($selectedMonth); ?>" data-picker-value>

        <div class="ticket-month-picker__bar">
            <button type="button" class="ticket-month-picker__trigger" data-picker-trigger aria-expanded="false" aria-haspopup="dialog">
                <span class="ticket-month-picker__trigger-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                <span class="ticket-month-picker__trigger-text">
                    <span class="ticket-month-picker__trigger-kicker">Período</span>
                    <span class="ticket-month-picker__trigger-value"><?php echo html($triggerValue); ?></span>
                </span>
                <i class="bi bi-chevron-down ticket-month-picker__trigger-chevron" aria-hidden="true"></i>
            </button>

            <?php if ($selectedMonth !== ''): ?>
                <span class="ticket-month-picker__chip">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <?php echo html($selectedLabel !== '' ? $selectedLabel : $selectedMonth); ?>
                </span>
                <?php if ($ticketMonthFilterResetUrl !== ''): ?>
                    <a href="<?php echo html($ticketMonthFilterResetUrl); ?>" class="ticket-month-picker__clear">Quitar</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="ticket-month-picker__panel" data-picker-panel role="dialog" aria-label="Elegir mes">
            <div class="ticket-month-picker__panel-head">
                <p class="ticket-month-picker__panel-title">Elegir mes</p>
                <div class="ticket-month-picker__year-nav">
                    <button type="button" class="ticket-month-picker__year-btn" data-picker-prev aria-label="Año anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="ticket-month-picker__year-label" data-picker-year><?php echo (int)$pickerInitialYear; ?></span>
                    <button type="button" class="ticket-month-picker__year-btn" data-picker-next aria-label="Año siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="ticket-month-picker__grid" role="group" aria-label="Meses del año">
                <?php for ($mi = 1; $mi <= 12; $mi++): ?>
                    <?php
                    $key = sprintf('%04d-%02d', $pickerInitialYear, $mi);
                    $isSel = ($selectedMonth === $key);
                    ?>
                    <button type="button"
                            class="ticket-month-picker__month<?php echo $isSel ? ' is-selected' : ''; ?>"
                            data-picker-month
                            data-month-index="<?php echo $mi; ?>"
                            data-month-key="<?php echo html($key); ?>"
                            <?php echo empty($availableMap[$key]) ? ' disabled' : ''; ?>
                            aria-label="<?php echo html($monthShort[$mi - 1] . ' ' . $pickerInitialYear); ?>">
                        <span><?php echo html($monthShort[$mi - 1]); ?></span>
                        <span class="ticket-month-picker__month-num"><?php echo $mi; ?></span>
                    </button>
                <?php endfor; ?>
            </div>

            <button type="button" class="ticket-month-picker__all" data-picker-all>
                <i class="bi bi-calendar-x me-1"></i> Ver todos los meses
            </button>
        </div>
    </form>
</div>
