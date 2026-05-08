<?PHP

namespace dashboard\MyWidgets\Simplidents;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Error;
use Throwable;

require_once('../../../includes/central.php');

class query extends Widget
    {
    public function getTitle(): string
        {
        return 'Simplidents Query';
        }

    public static function execute()
        {
        try {
            $widget = new static();
            $einheit = isset($_GET['einheit']) ? $_GET['einheit'] : '';
            $username = isset($_GET['username']) ? $_GET['username'] : '';

            $incidents = $widget->getIncidents($einheit, $username);
            echo $incidents;
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

    public function getIncidents($einheit, $username)
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

        $where = "
                    j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND (j.STATUS = 0 OR j.STATUS = 1)
                    AND i.status = 0
                    AND j.STEP IN (1, 2, 3, 4, 17, 5, 807, 802, 30, 40, 50, 15)
                ";

        if (!empty($username)) {
            $where .= " AND j.username LIKE '" . $username . "%'";
            }

        if ($einheit != "Alle") {
            $where .= " AND r.EINHEITSNUMMER = '" . $einheit . "'";
            }

        $temp = "
                    SELECT j.STEP, COUNT(j.STEP) AS STEP_COUNT, j.steplabel
                    FROM JRINCIDENTS j
                    INNER JOIN JRINCIDENT i ON j.processid = i.processid
                    LEFT JOIN RE_HEAD r ON j.process_step_id = r.step_id
                    WHERE $where
                    GROUP BY j.STEP
                ";

        $result = $JobDB->query($temp);

        $incidents = array_fill(0, 12, 0);
        while ($row = $JobDB->fetchRow($result)) {
            switch ($row["STEP"]) {
                case "1":
                    $incidents[0] = $row["STEP_COUNT"];
                    break;
                case "2":
                    $incidents[1] = $row["STEP_COUNT"];
                    break;
                case "3":
                    $incidents[2] = $row["STEP_COUNT"];
                    break;
                case "4":
                case "7":
                    $incidents[3] = (string) ((int) $incidents[3] + (int) $row["STEP_COUNT"]);
                    break;
                case "17":
                    $incidents[4] = $row["STEP_COUNT"];
                    break;
                case "5":
                    $incidents[5] = $row["STEP_COUNT"];
                    break;
                case "807":
                    $incidents[6] = $row["STEP_COUNT"];
                    break;
                case "802":
                    $incidents[7] = (string) ((int) $incidents[5] + (int) $row["STEP_COUNT"]);
                    break;
                case "30":
                    $incidents[8] = $row["STEP_COUNT"];
                    break;
                case "40":
                    $incidents[9] = $row["STEP_COUNT"];
                    break;
                case "50":
                    $incidents[10] = $row["STEP_COUNT"];
                    break;
                case "15":
                    $incidents[11] = $row["STEP_COUNT"];
                    break;
                default:
                    break;
                }
            }
        array_unshift($incidents, (string) array_sum($incidents));

        return json_encode($incidents);
        }
    }

query::execute();
