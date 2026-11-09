-- Select an empty project database before importing this file.
CREATE TABLE mechanics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    car_registration VARCHAR(30) NOT NULL,
    engine_number VARCHAR(50) NOT NULL,
    appointment_date DATE NOT NULL,
    mechanic_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id),
    UNIQUE (phone, appointment_date),
    INDEX (mechanic_id, appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO mechanics (name)
VALUES ('Mechanic 1'), ('Mechanic 2'), ('Mechanic 3'),
       ('Mechanic 4'), ('Mechanic 5');
