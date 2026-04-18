<?php
/**
 * Central auth + permission helpers.
 * Include this file after session_start() and config.php.
 */

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function loadPermissionsForRole($conn, $role) {
    $permissions = [];

    if (!$role) {
        return $permissions;
    }

    $stmt = $conn->prepare("SELECT permission FROM role_permissions WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['permission'];
    }

    $stmt->close();
    return $permissions;
}

function setSessionPermissions($conn, $role) {
    $_SESSION['permissions'] = loadPermissionsForRole($conn, $role);
}

function hasPermission($permission) {
    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        return false;
    }

    return in_array($permission, $_SESSION['permissions']);
}

function hasAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }

    return false;
}

function requirePermission($permission, $redirect = 'home.php') {
    if (!hasPermission($permission)) {
        $target = strpos($redirect, '?') === false ? ($redirect . '?unauthorized=1') : ($redirect . '&unauthorized=1');
        header('Location: ' . $target);
        exit();
    }
}

function requireAnyPermission($permissions, $redirect = 'home.php') {
    if (!hasAnyPermission($permissions)) {
        $target = strpos($redirect, '?') === false ? ($redirect . '?unauthorized=1') : ($redirect . '&unauthorized=1');
        header('Location: ' . $target);
        exit();
    }
}

