<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection
$servername = "yourservername";
$database = "yourdatabasename";
$username = "yourusername";
$password = "yourpassword";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(array("message" => "Connection failed: " . $conn->connect_error)));
}

// Get HTTP method and request path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Helper function for GET requests
function getAndReturnResults($conn, $sql, $params = null) {
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    if ($result && $result->num_rows > 0) {
        $data = array();
        while($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        echo json_encode($data);
    } else {
        echo json_encode(array("message" => "No data found."));
    }
}

// Basic routing
switch($method) {
    case 'GET':
        // Button Settings
        if (strpos($path, '/api/button-settings') !== false) {
            $sql = "SELECT * FROM ButtonSettings WHERE SiteID = 'YourSiteID'";
            getAndReturnResults($conn, $sql);
        }
        // Field Settings
        elseif (strpos($path, '/api/field-settings') !== false) {
            $sql = "SELECT * FROM FieldSettings WHERE SiteID = 'YourSiteID'";
            getAndReturnResults($conn, $sql);
        }
        // Site Details
        elseif (strpos($path, '/api/site-details') !== false) {
            $sql = "SELECT * FROM SiteDetails WHERE SiteID = 'YourSiteID'";
            getAndReturnResults($conn, $sql);
        }
        // Day Start Questions
        elseif (strpos($path, '/api/day-start-questions') !== false) {
            $sql = "SELECT * FROM DayStartQuestions WHERE SiteID = 'YourSiteID' ORDER BY SequenceNo";
            getAndReturnResults($conn, $sql);
        }
        // Submit Questions
        elseif (strpos($path, '/api/submit-questions') !== false) {
            $sql = "SELECT * FROM SubmitQuestions WHERE SiteID = 'YourSiteID' ORDER BY SequenceNo";
            getAndReturnResults($conn, $sql);
        }
        // Timesheet Tasks
        elseif (strpos($path, '/api/timesheet-tasks') !== false) {
            $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
            $forDate = isset($_GET['forDate']) ? $_GET['forDate'] : '';
            
            if ($userId && $forDate) {
                $sql = "SELECT * FROM TimeSheetTasks WHERE SiteID = 'YourSiteID' AND UserID = ? AND ForDate = ? ORDER BY StartTime";
                getAndReturnResults($conn, $sql, array("ss", $userId, $forDate));
            } else {
                echo json_encode(array("message" => "UserID and ForDate are required."));
            }
        }
        // Timesheets
        elseif (strpos($path, '/api/timesheets') !== false) {
            $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
            
            if ($userId) {
                $sql = "SELECT * FROM Timesheets WHERE SiteID = 'YourSiteID' AND UserID = ? ORDER BY ForDate DESC";
                getAndReturnResults($conn, $sql, array("s", $userId));
            } else {
                $sql = "SELECT * FROM Timesheets WHERE SiteID = 'YourSiteID' ORDER BY ForDate DESC";
                getAndReturnResults($conn, $sql);
            }
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        // Operator Login
        if (strpos($path, '/api/login') !== false) {
            if(!empty($data->UserName) && !empty($data->PIN)) {
                $sql = "SELECT * FROM Operators WHERE SiteID = 'YourSiteID' AND UserName = ? AND PIN = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $data->UserName, $data->PIN);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $operator = $result->fetch_assoc();
                    echo json_encode(array("success" => true, "operator" => $operator));
                } else {
                    echo json_encode(array("success" => false, "message" => "Invalid credentials"));
                }
            }
        }
        // Create Timesheet
        elseif (strpos($path, '/api/timesheet') !== false) {
            if(!empty($data->UserID) && !empty($data->ForDate)) {
                $sql = "INSERT INTO Timesheets (SiteID, UserID, ForDate, SubmitTime) VALUES ('YourSiteID', ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $data->UserID, $data->ForDate);
                
                if($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Timesheet created"));
                } else {
                    echo json_encode(array("success" => false, "message" => "Failed to create timesheet"));
                }
            }
        }
        // Create Timesheet Task
        elseif (strpos($path, '/api/timesheet-task') !== false) {
            if(!empty($data->UserID) && !empty($data->ForDate) && !empty($data->StartTime) && !empty($data->TimeFor)) {
                $sql = "INSERT INTO TimeSheetTasks (SiteID, UserID, ForDate, StartTime, FinishTime, TimeFor, JobNo, ReferenceNo1, ReferenceNo2, ReferenceNo3, WorkDone) 
                        VALUES ('YourSiteID', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssss", 
                    $data->UserID, 
                    $data->ForDate,
                    $data->StartTime,
                    $data->FinishTime,
                    $data->TimeFor,
                    $data->JobNo,
                    $data->ReferenceNo1,
                    $data->ReferenceNo2,
                    $data->ReferenceNo3,
                    $data->WorkDone
                );
                
                if($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Task created"));
                } else {
                    echo json_encode(array("success" => false, "message" => "Failed to create task"));
                }
            }
        }
        // Save Day Start Questions
        elseif (strpos($path, '/api/day-start-answers') !== false) {
            if(!empty($data->UserID) && !empty($data->ForDate) && !empty($data->answers)) {
                $success = true;
                foreach($data->answers as $answer) {
                    $sql = "INSERT INTO TimesheetDayStartQuestions (SiteID, UserID, ForDate, SequenceNo, ResponseText) 
                            VALUES ('YourSiteID', ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $data->UserID, $data->ForDate, $answer->SequenceNo, $answer->ResponseText);
                    if(!$stmt->execute()) {
                        $success = false;
                        break;
                    }
                }
                echo json_encode(array("success" => $success, "message" => $success ? "Answers saved" : "Failed to save answers"));
            }
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        // Update Timesheet
        if (strpos($path, '/api/timesheet') !== false) {
            if(!empty($data->UserID) && !empty($data->ForDate)) {
                $sql = "UPDATE Timesheets SET Comments = ?, DayOffReason = ?, UploadTime = NOW() 
                        WHERE SiteID = 'YourSiteID' AND UserID = ? AND ForDate = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $data->Comments, $data->DayOffReason, $data->UserID, $data->ForDate);
                
                if($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Timesheet updated"));
                } else {
                    echo json_encode(array("success" => false, "message" => "Failed to update timesheet"));
                }
            }
        }
        // Update Timesheet Task
        elseif (strpos($path, '/api/timesheet-task') !== false) {
            if(!empty($data->ID)) {
                $sql = "UPDATE TimeSheetTasks SET FinishTime = ?, TimeFor = ?, JobNo = ?, 
                        ReferenceNo1 = ?, ReferenceNo2 = ?, ReferenceNo3 = ?, WorkDone = ? 
                        WHERE ID = ? AND SiteID = 'YourSiteID'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssi", 
                    $data->FinishTime,
                    $data->TimeFor,
                    $data->JobNo,
                    $data->ReferenceNo1,
                    $data->ReferenceNo2,
                    $data->ReferenceNo3,
                    $data->WorkDone,
                    $data->ID
                );
                
                if($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Task updated"));
                } else {
                    echo json_encode(array("success" => false, "message" => "Failed to update task"));
                }
            }
        }
        break;

    case 'DELETE':
        // Delete Timesheet Task
        if (strpos($path, '/api/timesheet-task') !== false) {
            $taskId = isset($_GET['id']) ? $_GET['id'] : '';
            if(!empty($taskId)) {
                $sql = "DELETE FROM TimeSheetTasks WHERE ID = ? AND SiteID = 'YourSiteID'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $taskId);
                
                if($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Task deleted"));
                } else {
                    echo json_encode(array("success" => false, "message" => "Failed to delete task"));
                }
            }
        }
        break;
}

$conn->close();
?>