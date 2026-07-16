<?php
function fetchData($conn, $startDate, $endDate, $queryType) {
    if ($queryType === 'vm') {
        $query = "
        WITH TareasUnicas AS (
            SELECT
                *,
                ROW_NUMBER() OVER(
                    PARTITION BY CONCAT(TIPO, ' ', VM) -- Changed to VM_NAME
                    ORDER BY jobs_id
                ) AS rn
            FROM
                TareasBackup
        )
        SELECT
            t1.job_summary_id,
            t1.outlook_email_entry_id,
            t1.email_subject,
            t1.job_name,
            t1.retry,
            t1.summary_success_count,
            t1.summary_warning_count,
            t1.summary_error_count,
            t1.job_start_time_str,
            t1.job_end_time_str,
            t1.job_duration_str,
            t1.vms_processed_summary_str,
            YEAR(t1.email_received_at) AS year,
            MONTH(t1.email_received_at) AS mes,
            DAY(t1.email_received_at) AS dia,
            HOUR(t1.email_received_at) AS hora,
            CASE DAYOFWEEK(t1.email_received_at)
                WHEN 1 THEN 'Do' -- Sunday
                WHEN 2 THEN 'Lu' -- Monday
                WHEN 3 THEN 'Ma' -- Tuesday
                WHEN 4 THEN 'Mi' -- Wednesday
                WHEN 5 THEN 'Ju' -- Thursday
                WHEN 6 THEN 'Vi' -- Friday
                WHEN 7 THEN 'Sa' -- Saturday
            END AS dia_semana,
            CASE
                WHEN t2.vm_status = 'Success' THEN 'OK'
                WHEN t2.vm_status = 'Error' THEN 'Failed'
                WHEN t2.vm_status = 'Warning' THEN 'Warning'
                ELSE 'Unknown'    END AS Succeeded,
            t2.*,
            tu.FRECUENCIA,
            tu.HORA

        FROM
            Backup_veeam_job AS t1
        JOIN
            Backup_veeam_vm AS t2 ON t1.job_summary_id = t2.job_summary_id
        LEFT JOIN
            TareasUnicas AS tu
            ON t2.vm_name = tu.VM -- Changed join condition to VM_NAME
            AND tu.rn = 1
            WHERE
            DATE(t1.email_received_at) >= ? AND DATE(t1.email_received_at) <= ?


        ORDER BY
            t1.email_received_at ASC";
    } elseif ($queryType === 'job') {
        $query = "
        WITH TareasUnicas AS (
            SELECT
                *,
                ROW_NUMBER() OVER(
                    PARTITION BY CONCAT(TIPO, ' ', JOB_NAME)
                    ORDER BY jobs_id
                ) AS rn
            FROM
                TareasBackup
        )
        SELECT
            t1.job_summary_id,
            t1.outlook_email_entry_id,
            t1.email_subject,
            t1.job_name,
            t1.retry,
            t1.summary_success_count,
            t1.summary_warning_count,
            t1.summary_error_count,
            t1.job_start_time_str,
            t1.job_end_time_str,
            t1.job_duration_str,
            t1.vms_processed_summary_str,
            YEAR(t1.email_received_at) AS year,
            MONTH(t1.email_received_at) AS mes,
            DAY(t1.email_received_at) AS dia,
            HOUR(t1.email_received_at) AS hora,
            DAYOFWEEK(t1.email_received_at) AS dia_semana, -- Day of the week (1=Sunday, 7=Saturday)
            CASE
                WHEN t2.vm_status = 1 THEN 'OK'
                WHEN t2.vm_status = 0 THEN 'Failed'
                ELSE 'Unknown'
            END AS Succeeded, -- Renamed from Succeeded to reflect actual status
            t2.*,
            tu.FRECUENCIA, -- Include FRECUENCIA from TareasUnicas
            tu.HORA -- Include HORA from TareasUnicas

        FROM
            Backup_veeam_job AS t1
        JOIN
            Backup_veeam_vm AS t2 ON t1.job_summary_id = t2.job_summary_id
        LEFT JOIN
            TareasUnicas AS tu
            ON REPLACE(t1.job_name, ' (Full)', '') = CONCAT(tu.TIPO, ' ', tu.JOB_NAME)
            AND tu.rn = 1
        WHERE
            DATE(t1.email_received_at) >= ? AND DATE(t1.email_received_at) <= ?
        ORDER BY
            t1.email_received_at ASC;";
    } else {
        return false;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();
    return $data;
}
?>