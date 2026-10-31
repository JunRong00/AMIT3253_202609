<?php
// TAR UMT's faculties and centres, used to populate the Faculty dropdown on
// registration and the account page instead of a free-text field.
function tarumt_faculties() {
    return [
        'Faculty of Accountancy, Finance and Business',
        'Faculty of Applied Sciences',
        'Faculty of Computing and Information Technology',
        'Faculty of Built Environment',
        'Faculty of Engineering and Technology',
        'Faculty of Communication and Creative Industries',
        'Faculty of Social Science and Humanities',
        'Centre for Pre-University Studies',
        'Centre for Postgraduate Studies and Research',
        'Centre for Continuing and Professional Education',
        'Centre for Business Incubation and Entrepreneurial Ventures',
        'SME Centre',
        'Student Career Development Centre',
        'Institute of Social Economic Research (ISER)',
    ];
}

// Topics a user can pick when leaving feedback (feedback.php).
function feedback_topics() {
    return [
        'Timetable',
        'Replacement Classes',
        'Venues',
        'Website',
    ];
}

// Falls back to a neutral placeholder until an admin uploads a real photo.
//
// An S3-stored photo's image_url is already a full https:// URL - returned
// as-is. A local-disk photo's image_url is root-relative ("/uploads/xxx.jpg")
// and gets turned into a path relative to the current script instead, because
// this app may be hosted as a subdirectory alongside sibling apps (not at the
// web server's document root) - a leading "/uploads/..." would then resolve
// to the wrong app's uploads folder (or nowhere).
function entity_image_url($row) {
    if (!empty($row['image_url'])) {
        if (str_starts_with($row['image_url'], 'https://') || str_starts_with($row['image_url'], 'http://')) {
            return $row['image_url'];
        }

        $relative = ltrim($row['image_url'], '/');
        $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';

        $path = __DIR__ . '/' . $relative;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';
        return $prefix . $relative . $version;
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">'
         . '<rect width="100%" height="100%" fill="#e1e5eb"/>'
         . '<text x="50%" y="50%" font-size="18" fill="#626a76" text-anchor="middle" dy=".3em">No photo yet</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Validates an uploaded photo, then stores it either on S3 (if AWS_S3_BUCKET
// is configured, see config.php) or on local disk (the default). Returns
// [webPath, error] - webPath is either a full S3 https:// URL or a
// root-relative "/uploads/xxx.jpg" path, or null if no file was uploaded or
// it failed.
function handle_image_upload($file, $uploadDir, $prefix = 'photo') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Image upload failed. Please try again.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return [null, 'Image must be smaller than 5MB.'];
    }

    // Check the actual file content, not just the extension/MIME the browser
    // claims, so a renamed .php file can't slip through.
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [null, 'The uploaded file is not a valid image.'];
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedMimes[$imageInfo['mime']])) {
        return [null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }

    $filename = uniqid($prefix . '_', true) . '.' . $allowedMimes[$imageInfo['mime']];

    if (AWS_S3_BUCKET !== '') {
        return s3_put_object($filename, file_get_contents($file['tmp_name']), $imageInfo['mime']);
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        return [null, 'Could not save the uploaded image.'];
    }

    return ['/uploads/' . $filename, null];
}

// Deletes a previously uploaded image, from S3 or local disk depending on
// which one image_url points at.
function delete_image_file($imageUrl, $uploadDir) {
    if (!$imageUrl) {
        return;
    }
    if (str_starts_with($imageUrl, 'https://') || str_starts_with($imageUrl, 'http://')) {
        s3_delete_object($imageUrl);
        return;
    }
    if (str_starts_with($imageUrl, '/uploads/')) {
        $path = $uploadDir . '/' . basename($imageUrl);
        if (is_file($path)) {
            unlink($path);
        }
    }
}

// ============================================================================
// S3 upload support (Signature Version 4, no AWS SDK/Composer dependency).
// Only used when AWS_S3_BUCKET is set in config.php - local disk is the
// default and needs none of this. The signing logic here is verified
// byte-for-byte against AWS's own published SigV4 test suite.
// ============================================================================

// Builds the canonical request + the list of header names that were signed,
// per the SigV4 spec: https://docs.aws.amazon.com/general/latest/gr/sigv4-create-canonical-request.html
function s3_canonical_request($method, $path, $headers, $payloadHash) {
    $sorted = $headers;
    ksort($sorted);
    $canonicalHeaders = '';
    foreach ($sorted as $name => $value) {
        $canonicalHeaders .= strtolower($name) . ':' . trim($value) . "\n";
    }
    $signedHeaders = implode(';', array_map('strtolower', array_keys($sorted)));
    $canonicalRequest = implode("\n", [$method, $path, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
    return [$canonicalRequest, $signedHeaders];
}

// Signs an S3 request and returns [host, headers] with the Authorization
// header already filled in.
function s3_sign($method, $bucket, $region, $key, $payload, $credentials) {
    $host = "$bucket.s3.$region.amazonaws.com";
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $payloadHash = hash('sha256', $payload);

    $headers = [
        'Host' => $host,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Content-Sha256' => $payloadHash,
    ];
    if (!empty($credentials['token'])) {
        $headers['X-Amz-Security-Token'] = $credentials['token'];
    }

    [$canonicalRequest, $signedHeaders] = s3_canonical_request($method, '/' . $key, $headers, $payloadHash);

    $service = 's3';
    $credentialScope = "$dateStamp/$region/$service/aws4_request";
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $credentialScope,
        hash('sha256', $canonicalRequest),
    ]);

    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $credentials['secret_key'], true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$credentials['access_key']}/$credentialScope, "
        . "SignedHeaders=$signedHeaders, Signature=$signature";

    return [$host, $headers];
}

// Gets S3 credentials one of two ways: first by asking the EC2 instance's
// own metadata service (IMDSv2) for whatever IAM role is attached - the
// preferred way, since those credentials are temporary and rotated
// automatically with nothing to leak. If there's no role to ask (e.g.
// running locally, or an AWS Academy Learner Lab where you can't attach
// one), falls back to explicit AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY/
// AWS_SESSION_TOKEN from config.php (set as environment variables, e.g.
// copied from a Learner Lab's "AWS Details" panel - never hardcoded/
// committed). Returns null if neither is available, quickly (short
// timeouts on the metadata service calls) so this never hangs a request.
function s3_instance_credentials() {
    $credentials = s3_role_credentials();
    if ($credentials) {
        return $credentials;
    }

    if (AWS_ACCESS_KEY_ID !== '' && AWS_SECRET_ACCESS_KEY !== '') {
        return [
            'access_key' => AWS_ACCESS_KEY_ID,
            'secret_key' => AWS_SECRET_ACCESS_KEY,
            'token' => AWS_SESSION_TOKEN,
        ];
    }

    return null;
}

// The IMDSv2 half of s3_instance_credentials() - split out so the fallback
// logic above stays easy to follow.
function s3_role_credentials() {
    $tokenCtx = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $tokenCtx);
    if ($token === false || $token === '') {
        return null;
    }

    $metaCtx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "X-aws-ec2-metadata-token: $token\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $roleName = trim((string)@file_get_contents(
        'http://169.254.169.254/latest/meta-data/iam/security-credentials/',
        false,
        $metaCtx
    ));
    if ($roleName === '') {
        return null;
    }

    $credsJson = @file_get_contents(
        "http://169.254.169.254/latest/meta-data/iam/security-credentials/$roleName",
        false,
        $metaCtx
    );
    $creds = $credsJson ? json_decode($credsJson, true) : null;
    if (!isset($creds['AccessKeyId'], $creds['SecretAccessKey'], $creds['Token'])) {
        return null;
    }

    return [
        'access_key' => $creds['AccessKeyId'],
        'secret_key' => $creds['SecretAccessKey'],
        'token' => $creds['Token'],
    ];
}

// Uploads $data to S3 under $key. Returns [publicUrl, error], matching the
// shape handle_image_upload()'s callers already expect.
function s3_put_object($key, $data, $contentType) {
    $credentials = s3_instance_credentials();
    if (!$credentials) {
        return [null, 'Could not get S3 credentials: no IAM role is attached to this instance, and '
            . 'AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY are not set either. See config.php.'];
    }

    [$host, $headers] = s3_sign('PUT', AWS_S3_BUCKET, AWS_S3_REGION, $key, $data, $credentials);
    $headers['Content-Type'] = $contentType;

    $headerLines = '';
    foreach ($headers as $name => $value) {
        $headerLines .= "$name: $value\r\n";
    }

    $context = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => $headerLines,
        'content' => $data,
        'timeout' => 20,
        'ignore_errors' => true,
    ]]);

    @file_get_contents("https://$host/$key", false, $context);
    $status = s3_response_status($http_response_header ?? []);

    if ($status !== 200) {
        return [null, "S3 upload failed (HTTP $status)."];
    }

    return ["https://$host/$key", null];
}

// Deletes an object previously uploaded to S3, given the URL stored in
// image_url. Does nothing if the URL doesn't belong to the configured
// bucket (defensive - shouldn't happen in practice).
function s3_delete_object($url) {
    $host = AWS_S3_BUCKET . '.s3.' . AWS_S3_REGION . '.amazonaws.com';
    $prefix = "https://$host/";
    if (!str_starts_with($url, $prefix)) {
        return;
    }
    $key = substr($url, strlen($prefix));

    $credentials = s3_instance_credentials();
    if (!$credentials) {
        return;
    }

    [, $headers] = s3_sign('DELETE', AWS_S3_BUCKET, AWS_S3_REGION, $key, '', $credentials);
    $headerLines = '';
    foreach ($headers as $name => $value) {
        $headerLines .= "$name: $value\r\n";
    }

    $context = stream_context_create(['http' => [
        'method' => 'DELETE',
        'header' => $headerLines,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://$host/$key", false, $context);
}

// Pulls the HTTP status code out of the $http_response_header array that
// PHP's stream wrapper populates after a file_get_contents() HTTP request.
function s3_response_status($responseHeaders) {
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            return (int)$m[1];
        }
    }
    return 0;
}

function class_types() {
    return ['Lecture', 'Tutorial', 'Practical'];
}

// Teaching days only - MySQL's WEEKDAY() is 0 for Monday, so day_of_week in
// timetable_entries is WEEKDAY() + 1.
function weekdays() {
    return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
}

function day_of_week($date) {
    return (int)date('N', strtotime($date));
}

function monday_of($date) {
    $ts = strtotime($date);
    return date('Y-m-d', strtotime('-' . (date('N', $ts) - 1) . ' days', $ts));
}

function time_slot_label($slot) {
    return date('g:i a', strtotime($slot['start_time'])) . ' - ' . date('g:i a', strtotime($slot['end_time']));
}

// True if the time slot on the given date has already started.
function is_slot_in_past($date, $startTime) {
    $start = strtotime($date . ' ' . $startTime);
    return $start !== false && $start < time();
}

function status_badge_class($status) {
    switch ($status) {
        case 'Approved': return 'badge-success';
        case 'Rejected': return 'badge-danger';
        default:         return 'badge-warning';
    }
}

// True if this lecturer teaches the given course.
function lecturer_owns_course($conn, $lecturerId, $courseId) {
    $stmt = $conn->prepare('SELECT 1 FROM courses WHERE id = ? AND lecturer_id = ?');
    $stmt->bind_param('ii', $courseId, $lecturerId);
    $stmt->execute();
    $owns = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $owns;
}

// IDs of venues already in use on $date during $slotId: either by a regular
// weekly class on that weekday, or by another replacement class that is
// Pending or Approved (a Pending request holds the venue until an admin
// decides, so two lecturers can't be promised the same room).
function busy_venue_ids($conn, $date, $slotId, $excludeReplacementId = 0) {
    $dow = day_of_week($date);
    $stmt = $conn->prepare("
        SELECT venue_id FROM timetable_entries WHERE day_of_week = ? AND time_slot_id = ?
        UNION
        SELECT venue_id FROM replacement_classes
        WHERE replacement_date = ? AND time_slot_id = ? AND status IN ('Pending', 'Approved') AND id != ?
    ");
    $stmt->bind_param('iisii', $dow, $slotId, $date, $slotId, $excludeReplacementId);
    $stmt->execute();
    $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'venue_id'));
    $stmt->close();
    return $ids;
}

// Validates a replacement class request (shared by create.php and edit.php).
// Returns an error message, or '' if the request is valid. Must be called
// inside a transaction that has already locked the venue row.
function replacement_error($conn, $courseId, $originalDate, $replacementDate, $slotId, $venueId, $excludeId = 0) {
    if ($originalDate === '' || $replacementDate === '') {
        return 'Please choose both the original class date and the replacement date.';
    }
    if (day_of_week($replacementDate) > 5) {
        return 'Replacement classes can only be held on weekdays.';
    }
    if ($replacementDate < date('Y-m-d')) {
        return 'The replacement date cannot be in the past.';
    }

    $stmt = $conn->prepare('SELECT start_time FROM time_slots WHERE id = ?');
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$slot) {
        return 'Please choose a valid time slot.';
    }
    if (is_slot_in_past($replacementDate, $slot['start_time'])) {
        return 'That time slot has already started today. Please choose a later slot or date.';
    }

    $dow = day_of_week($originalDate);
    $stmt = $conn->prepare('SELECT 1 FROM timetable_entries WHERE course_id = ? AND day_of_week = ?');
    $stmt->bind_param('ii', $courseId, $dow);
    $stmt->execute();
    $hasClass = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$hasClass) {
        return 'This course has no regular class on ' . date('l, d M Y', strtotime($originalDate)) . ' - pick the date of the class being replaced.';
    }

    if (in_array($venueId, busy_venue_ids($conn, $replacementDate, $slotId, $excludeId), true)) {
        return 'That venue is already in use at this time. Please choose another venue or slot.';
    }
    return '';
}
