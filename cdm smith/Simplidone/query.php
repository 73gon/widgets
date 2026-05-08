<?PHP

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use DateTime;
use DateTimeZone;
use Error;
use Throwable;

require_once('../../../includes/central.php');

class query extends Widget
    {
    public function getTitle(): string
        {
        return 'Simplidone Query';
        }

    public static function execute()
        {
        try {
            $widget = new static();

            $indate = isset($_GET['indate']) ? $_GET['indate'] : '';
            $outdate = isset($_GET['outdate']) ? $_GET['outdate'] : '';
            $einheit = isset($_GET['einheit']) ? $_GET['einheit'] : '';
            $username = isset($_GET['username']) ? $_GET['username'] : '';

            $indate = empty($indate) ? '2015-01-01' : $indate;
            $outdate = empty($outdate) ? date('Y-m-d') : $outdate;

            $all = $widget->getIncidents($indate, $outdate, $einheit, $username);
            echo $all;
            } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            } catch (Error $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fatal Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Throwable: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }
        }

    public function getIncidents($indate, $outdate, $einheit, $username)
        {
        $JobDB = $this->getJobDB();
        if (!empty($username)) {
            $query = "SELECT * FROM JRUSERS WHERE username = '$username'";
            $result = $JobDB->query($query);
            $count = 0;
            while ($row = $JobDB->fetchRow($result)) {
                $count++;
                }
            if ($count === 0) {
                return "false";
                }
            }

        $normalSteps = $this->getNormalSteps($indate, $outdate, $einheit, $username);
        $payments = $this->getPayments($indate, $outdate, $einheit);

        $incidents = array_merge($normalSteps, $payments);

        $sum = array_fill(0, count($payments[0]), 0);
        for ($i = 0; $i < count($payments); $i++) {
            $sum[0] += $payments[$i][0];
            }

        array_unshift($incidents, $sum);
        array_unshift($incidents, ["Anzahl Rechnungen", "Summe Dauer", "Durchschnitt Dauer"]);

        for ($i = 0; $i < count($incidents); $i++) {
            if ($incidents[$i][0] == "0") {
                $incidents[$i][1] = "-";
                $incidents[$i][2] = "-";
                }
            }

        for ($i = 2; $i < count($incidents); $i++) {
            if ($incidents[$i][0] != "0") {
                $incidents[1][1] = $this->addTimes([$incidents[1][1], $incidents[$i][1]]);
                $incidents[1][2] = $this->addTimes([$incidents[1][2], $incidents[$i][2]]);
                }
            }

        return json_encode($incidents);
        }

    public function getNormalSteps($indate, $outdate, $einheit, $username)
        {
        $JobDB = $this->getJobDB();

        $where = "
                j.STEP IN (1, 2, 3, 4, 17, 7, 5, 30, 40, 50, 15)
                AND (
                        (j.STEP = 15 AND r.STATUS = 'erledigt')
                        OR
                        (j.STEP != 15 AND r.STATUS = 'gezahlt')
                    )
                AND j.processname = 'RECHNUNGSBEARBEITUNG'
                AND j.indate IS NOT NULL
                AND j.outdate IS NOT NULL
                AND (j.indate >= '" . $indate . "' AND j.indate < '" . $outdate . "')
            ";

        if (!empty($username)) {
            $where .= " AND j.username LIKE '" . $username . "%'";
            }

        if ($einheit != "Alle") {
            $where .= " AND h.EINHEITSNUMMER = '" . $einheit . "'";
            }

        $query = "
            SELECT j.indate, j.outdate,
              CASE
                WHEN j.STEP = 4 AND h.ZAHLMETHODE = 'Kreditkarte' THEN 444
                ELSE j.STEP
              END AS STEP
            FROM JRINCIDENTS j
            INNER JOIN RE_HEAD h ON j.process_step_id = h.step_id
            INNER JOIN RECHNUGNEN r ON h.DOKUMENTENID = r.DOKUMENTENID
            WHERE $where
            GROUP BY j.STEP, r.DOKUMENTENID
        ";
        $result = $JobDB->query($query);

        $stepData = [];
        $stepMap = [
            "1" => 0,
            "2" => 1,
            "3" => 2,
            "4" => 3,
            "444" => 4,
            "17" => 5,
            "7" => 6,
            "5" => 7,
            "30" => 8,
            "40" => 9,
            "50" => 10,
            "15" => 11
        ];

        foreach ($stepMap as $step => $index) {
            $stepData[$index] = ['total_seconds' => 0, 'count' => 0];
            }

        $defaultTimeZone = new DateTimeZone('Europe/Berlin');

        while ($row = $JobDB->fetchRow($result)) {
            $step = $row["STEP"];
            if (isset($stepMap[$step])) {
                $index = $stepMap[$step];
                try {
                    $inDateTime = new DateTime($row['indate'], $defaultTimeZone);
                    $outDateTime = new DateTime($row['outdate'], $defaultTimeZone);

                    $businessSeconds = $this->calculateBusinessSeconds($inDateTime, $outDateTime);

                    $stepData[$index]['total_seconds'] += $businessSeconds;
                    $stepData[$index]['count']++;
                    } catch (Exception $e) {
                    continue;
                    }
                }
            }

        $incidents = array_fill(0, 12, array_fill(0, 3, 0));

        foreach ($stepData as $index => $data) {
            $count = $data['count'];
            $totalSeconds = $data['total_seconds'];

            if ($count > 0) {
                $avgSeconds = $totalSeconds / $count;
                $incidents[$index] = [
                    $count,
                    $this->calculateTime($totalSeconds),
                    $this->calculateTime($avgSeconds)
                ];
                } else {
                $incidents[$index] = [0, "0d: 0h: 0m", "0d: 0h: 0m"];
                }
            }

        return $incidents;
        }

    public function getPayments($indate, $outdate, $einheit)
        {
        $JobDB = $this->getJobDB();

        if ($einheit != "Alle") {
            $query = "
                        SELECT
                            CASE
                                WHEN r.RECHNUNGSFAELLIGKEIT >= j.enddate THEN
                                        TIMESTAMPDIFF(SECOND, j2.startdate, j.enddate)
                                ELSE NULL
                                END as notOverdue,
                            CASE
                                WHEN r.RECHNUNGSFAELLIGKEIT < j.enddate THEN
                                    TIMESTAMPDIFF(SECOND, r.RECHNUNGSFAELLIGKEIT, j.enddate)
                                ELSE NULL
                                END AS overdue
                        FROM RECHNUGNEN r
                        LEFT JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
                        LEFT JOIN JRINCIDENT j2 ON r.VORGANGSNUMMER = j2.incident
                        WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                        AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                        AND (j.startdate >= '" . $indate . "' AND j.startdate < '" . $outdate . "')
                        AND r.EINHEIT = '" . $einheit . "'
                        AND r.STATUS = 'gezahlt'
                        GROUP BY r.DOKUMENTENID
                    ";
            } else {
            $query = "
                        SELECT
                            CASE
                                WHEN r.RECHNUNGSFAELLIGKEIT >= j.enddate THEN
                                        TIMESTAMPDIFF(SECOND, j2.startdate, j.enddate)
                                ELSE NULL
                                END as notOverdue,
                            CASE
                                WHEN r.RECHNUNGSFAELLIGKEIT < j.enddate THEN
                                    TIMESTAMPDIFF(SECOND, r.RECHNUNGSFAELLIGKEIT, j.enddate)
                                ELSE NULL
                                END AS overdue
                        FROM RECHNUGNEN r
                        LEFT JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
                        LEFT JOIN JRINCIDENT j2 ON r.VORGANGSNUMMER = j2.incident
                        WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                        AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                        AND (j.startdate >= '" . $indate . "' AND j.startdate < '" . $outdate . "')
                        AND r.STATUS = 'gezahlt'
                        GROUP BY r.DOKUMENTENID
                    ";
            }
        $result = $JobDB->query($query);

        $overdue = $notOverdue = ['sum' => 0, 'avg' => 0, 'amount' => 0];

        while ($row = $JobDB->fetchRow($result)) {
            foreach (['notOverdue', 'overdue'] as $key) {
                if ($row[$key] !== null) {
                    ${$key}['sum'] += $row[$key];
                    ${$key}['amount']++;
                    }
                }
            }
        $notOverdue['avg'] = $notOverdue['amount'] == 0 ? 0 : $notOverdue['sum'] / $notOverdue['amount'];
        $overdue['avg'] = $overdue['amount'] == 0 ? 0 : $overdue['sum'] / $overdue['amount'];

        return [
            [$notOverdue['amount'], $this->calculateTime($notOverdue['sum']), $this->calculateTime($notOverdue['avg'])],
            [$overdue['amount'], $this->calculateTime($overdue['sum']), $this->calculateTime($overdue['avg'])]
        ];
        }

    public function addTimes($times)
        {
        $totalSeconds = 0;

        foreach ($times as $time) {
            list($days, $hours, $minutes, $seconds) = sscanf($time, "%dd: %dh: %dm: %ds");
            $totalSeconds += $seconds + $minutes * 60 + $hours * 3600 + $days * 86400;
            }

        $days = floor($totalSeconds / 86400);
        $totalSeconds %= 86400;
        $hours = floor($totalSeconds / 3600);
        $totalSeconds %= 3600;
        $minutes = floor($totalSeconds / 60);
        $seconds = $totalSeconds % 60;

        return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
        }

    public function calculateBusinessSeconds(DateTime $start, DateTime $end): int
        {
        if ($end <= $start) {
            return 0;
            }

        $businessSeconds = 0;
        $current = clone $start;

        while ($current < $end) {
            $dayOfWeek = (int) $current->format('N');

            $nextDay = (clone $current)->modify('+1 day')->setTime(0, 0, 0);
            $intervalEnd = min($end, $nextDay);

            if ($dayOfWeek < 6) {
                if ($intervalEnd > $current) {
                    $secondsThisSegment = $intervalEnd->getTimestamp() - $current->getTimestamp();
                    $businessSeconds += max(0, $secondsThisSegment);
                    }
                }
            $current = $nextDay;
            if ($current >= $end) {
                break;
                }
            }
        return $businessSeconds;
        }

    public function calculateTime($time)
        {
        $time = round($time);
        if ($time < 0)
            $time = 0;

        $days = floor($time / 86400);
        $hours = floor(($time % 86400) / 3600);
        $minutes = floor(($time % 3600) / 60);

        return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
        }
    }

// Execute the widget when this file is accessed directly
query::execute();
