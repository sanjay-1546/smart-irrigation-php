# PROJECT_PROMPT.md

## Project Name

Smart Farm Irrigation Backend

## Objective

Build a production-ready PHP backend for a Smart Farm Irrigation System.

The backend will run on shared hosting and expose REST APIs for:

* NodeMCU ESP8266 devices
* Web Dashboard
* Flutter Mobile App

## Technology Stack

* PHP 8.2
* MySQL 8
* PDO Database Connection
* REST API
* JWT Authentication
* Apache Shared Hosting
* cPanel Compatible

## Folder Structure

/backend

/config
/api
/models
/services
/middleware
/uploads
/logs

## Features

### Authentication

* Login
* Logout
* JWT Token
* Password Hashing
* Role Based Access

Roles:

* Admin
* Farmer
* Technician

### Device Management

Register NodeMCU Device

Fields:

* device_id
* farm_name
* firmware_version
* last_seen

### Farm Management

Create Farm

Fields:

* farm_name
* location
* owner_name

### Zone Management

Support 4 irrigation zones.

Fields:

* zone_name
* moisture_threshold
* crop_type

### Pump Management

Water Sources

* Borewell
* Open Well

Control:

* ON
* OFF

### Sensor APIs

Receive data from NodeMCU

POST:

/api/upload_sensor.php

Data:

* moisture_zone1
* moisture_zone2
* moisture_zone3
* moisture_zone4
* temperature
* humidity
* water_level
* flow_rate

Store all readings.

### Command APIs

NodeMCU polls every 10 seconds.

GET:

/api/get_commands.php

Return:

* bore_pump
* well_pump
* zone1
* zone2
* zone3
* zone4

### Scheduler

Create irrigation schedules.

Fields:

* start_time
* end_time
* zone
* water_source

### Weather Integration

Use OpenWeatherMap API.

Store:

* temperature
* humidity
* rainfall
* rain_probability

### Automation Engine

Rules:

IF moisture < threshold
THEN irrigate

IF rain_probability > 70
THEN skip irrigation

IF water_level < 20%
THEN stop all pumps

IF flow_rate = 0
THEN generate alert

### Alerts

Generate:

* Low Water
* Dry Soil
* Pump Failure
* No Flow
* Sensor Failure

### Reports

Daily

Weekly

Monthly

Water Consumption

Pump Runtime

Irrigation History

### API Documentation

Generate Swagger-style documentation page.

### Security

* Prepared Statements
* CSRF Protection
* XSS Protection
* Input Validation
* Rate Limiting

### Database

Generate complete MySQL schema and migration scripts.

### Deliverables

* Complete PHP Project
* SQL Dump
* API Documentation
* Installation Guide
* Shared Hosting Deployment Guide