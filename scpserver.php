<?php
/*  Program
    Configs     */

# Server Status: 
#   public: Everyone has access
#   maintenance: Client side app will not connect to the database
#   closed: The server will deny every endpoint except the status one
define("STATUS", "public");

# Databases constants
define("DB_HOST", "host");
define("DB_USER", "username");
define("DB_NAME", "db_name");
define("DB_PASSWORD", "password");

# URL to send with every file links
define("URL", "https://myhost.com/"); # Must End with a slash / and start by either https:// or http://
    
/*  Program
    Constants   */
 
define("SERVER_VERSION", "SCPServer.php[1.1-Beta]");
define("REQUIRED_CLIENT", ["warn" => "SCPTerminal1.0-b-0", "required" => "SCPTerminal1.0-b-0"]);

/*  Program 
    Functions   */

function get_parameter($field) {
  if (isset($_GET[$field])) {
    if (strlen($_GET[$field]) > 150) die();
    return $_GET[$field];
    }
  else return null;
}

function endpoints($endpoint) {
    if ($endpoint == "status") {
        return ["status" => STATUS,"required_client" => REQUIRED_CLIENT, "server_version" => SERVER_VERSION];
    }
    elseif (STATUS != "closed") {
        if ($endpoint == "connect") {
            
            $auth_data = ["auth" => get_parameter('auth'), "password" => get_parameter('password')];
            if ($auth_data["auth"] == null) return ["error" => 400, "err_message" => "Auth ID not defined", "server_version" => SERVER_VERSION];
            if ($auth_data["password"] == null) return ["error" => 400, "err_message" => "Password not defined", "server_version" => SERVER_VERSION];
    
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) return ["error" => 500, "err_message" => "Server Side Error: Database access forbidden", "server_version" => SERVER_VERSION];
    
            $hash = hash("sha256", $auth_data["password"], false);
    
            $result = $conn->prepare("SELECT * FROM users WHERE `username`=?");
            $result->bind_param("s", $username);

            $username = $auth_data["auth"];
            
            $result->execute();

            $result = $result->get_result();

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    if ($row["password"] != $hash) return ["error" => 403, "err_message" => "Access Forbidden: Invalid Password"];
                    return ["request" => "success", "connection_data" => ["username" => $row["username"],"token" => $row["token"], "permission_level" => $row["permission"]], "server_version" => SERVER_VERSION];
                }
            } 
            else return ["error" => 403, "err_message" => "Access Forbidden: Unknown User", "server_version" => SERVER_VERSION];
    
        }
        elseif ($endpoint == "get_report") {
            $get_data = ["token" => get_parameter('token'), "file_name" => get_parameter('file_name')];
            if ($get_data["token"] == null) return ["error" => 400, "err_message" => "User token is not defined", "server_version" => SERVER_VERSION];
            if ($get_data["file_name"] == null) return ["error" => 400, "err_message" => "File name is not defined", "server_version" => SERVER_VERSION];

            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) return ["error" => 500, "err_message" => "Server Side Error: Database access forbidden", "server_version" => SERVER_VERSION];

            $result = $conn->prepare("SELECT permission FROM users WHERE `token`=?");
            $result->bind_param("s", $token);
            $token = $get_data["token"];
            $result->execute();
            $result = $result->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $result = $conn->prepare("SELECT * FROM reports WHERE name=?");
                    $result->bind_param("s", $filename);
                    $filename = $get_data["file_name"];
                    $result->execute();
                    $result = $result->get_result();
                    if ($result->num_rows > 0) {
                        while ($row2 = $result->fetch_assoc()) {
                            if (!($row >= $row2["level"])) return ["error" => 403, "err_message" => "Access Forbidden: Insufficient Permissions", "server_version" => SERVER_VERSION];
                            return ["request" => "success", "report_data" => ["file_name" => URL . $row2["file"],"permission_level" => $row2["level"]], "server_version" => SERVER_VERSION];
                        }
                    }
                    else return ["error" => 404, "err_message" => "Unknown Report", "server_version" => SERVER_VERSION];
                }
            }
            else return ["error" => 403, "err_message" => "Access Forbidden: Unknown Token", "server_version" => SERVER_VERSION];
        }
        elseif ($endpoint == "get_user") {
            $get_data = ["token" => get_parameter('token'), "username" => get_parameter('username')];
            if ($get_data["token"] == null) return ["error" => 400, "err_message" => "User token is not defined", "server_version" => SERVER_VERSION];
            if ($get_data["token"] == null) return ["error" => 400, "err_message" => "Username is not defined", "server_version" => SERVER_VERSION];

            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) return ["error" => 500, "err_message" => "Server Side Error: Database access forbidden", "server_version" => SERVER_VERSION];

            $result = $conn->prepare("SELECT permission FROM users WHERE `token`=?");
            $result->bind_param("s", $token);
            $token = $get_data["token"];
            $result->execute();
            $result = $result->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $row = $row["permission"];
                    $result = $conn->prepare("SELECT username, full_name, rank, permission FROM users WHERE `username`=?");
                    $result->bind_param("s", $username);
                    $username = $get_data["username"];
                    $result->execute();
                    $result = $result->get_result();
                    if ($result->num_rows > 0) {
                        while ($row2 = $result->fetch_assoc()) {
                            if (!($row >= $row2["permission"])) return ["error" => 403, "err_message" => "Access Forbidden: Insufficient Permissions", "server_version" => SERVER_VERSION];
                            return ["request" => "success", "user_data" => $row2, "server_version" => SERVER_VERSION];
                        }
                    }
                    else return ["error" => 404, "err_message" => "Unknown User", "server_version" => SERVER_VERSION];
                }
            }
            else return ["error" => 403, "err_message" => "Access Forbidden: Unknown Token", "server_version" => SERVER_VERSION];
        }
        elseif ($endpoint == "list_reports") {
            $token = get_parameter("token");
            if ($token == null) return ["error" => 400, "err_message" => "User token is not defined", "server_version" => SERVER_VERSION];

            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) return ["error" => 500, "err_message" => "Server Side Error: Database access forbidden", "server_version" => SERVER_VERSION];

            $result = $conn->query("SELECT permission FROM users WHERE `token`=\"$token\"");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $row = $row["permission"];
                    $result = $conn->prepare("SELECT `name` FROM `reports` WHERE `level` <= ?");
                    $result->bind_param("s", $row2);
                    $row2 = $row;
                    $result->execute();
                    $result = $result->get_result();
                    if ($result->num_rows > 0) {
                        $reports_array = array();
                        while ($row = $result->fetch_assoc()) array_push($reports_array, $row["name"]);
                        return ["request" => "success", "reports" => $reports_array, "server_version" => SERVER_VERSION];
                    }
                    else return ["error" => 404, "err_message" => "No such reports to view", "server_version" => SERVER_VERSION];
                }
            }
            else return ["error" => 403, "err_message" => "Access Forbidden: Unknown Token", "server_version" => SERVER_VERSION];
        }
        elseif ($endpoint == "list_users") {
            $token = get_parameter("token");
            if ($token == null) return ["error" => 400, "err_message" => "User token is not defined", "server_version" => SERVER_VERSION];

            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) return ["error" => 500, "err_message" => "Server Side Error: Database access forbidden", "server_version" => SERVER_VERSION];

            $result = $conn->prepare("SELECT permission FROM users WHERE `token`=?");
            $result->bind_param("s", $token1);
            $token1 = $token;
            $result->execute();
            $result = $result->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $row = $row["permission"];
                    $result = $conn->prepare("SELECT `username`, `permission`, `full_name`, `rank` FROM `users` WHERE `permission` <= ?");
                    $result->bind_param("s", $row2);
                    $row2 = $row;
                    $result->execute();
                    $result = $result->get_result();
                    if ($result->num_rows > 0) {
                        $users_array = array();
                        while ($row = $result->fetch_assoc()) array_push($users_array, $row);
                        return ["request" => "success", "users" => $users_array, "server_version" => SERVER_VERSION];
                    }
                    else return ["error" => 404, "err_message" => "No such users to view", "server_version" => SERVER_VERSION];
                }
            }
            else return ["error" => 403, "err_message" => "Access Forbidden: Unknown Token", "server_version" => SERVER_VERSION];
        }
        elseif ($endpoint == null) return ["error" => 400, "err_message" => "No endpoint defined", "server_version" => SERVER_VERSION];
        else return ["error" => 404, "err_message" => "Unknown endpoint", "server_version" => SERVER_VERSION];
    }
    else return ["error" => 403, "err_message" => "The API status is actually closed"];
}

$data = endpoints(get_parameter('endpoint'));

header('Content-Type: application/json');
echo json_encode($data);
die();
?>
