package com.reschedule.student.data.model

import androidx.room.Entity
import androidx.room.PrimaryKey

data class LoginRequest(
    val username: String,
    val password: String
)

data class LoginResponse(
    val status: String,
    val message: String?,
    val token: String?,
    val user: UserDto?
)

data class UserDto(
    val id: Int,
    val program_id: Int,
    val fullname: String,
    val username: String,
    val programme_name: String,
    val year: String?,
    val semester: String?,
    val role: String?
)

@Entity(tableName = "schedules")
data class ScheduleEntity(
    @PrimaryKey val id: Int,
    val course_code: String,
    val course_name: String,
    val day_of_week: String,
    val start_time: String,
    val end_time: String,
    val room_name: String,
    val type: String,
    val year_level: Int,
    val semester: Int,
    val date: String? = null
)

@Entity(tableName = "requests")
data class RequestEntity(
    @PrimaryKey val id: Int,
    val course_code: String,
    val status: String,
    val reason: String,
    val review_note: String?
)

@Entity(tableName = "events")
data class EventEntity(
    @PrimaryKey(autoGenerate = true) val localId: Int = 0,
    val title: String,
    val event_type: String,
    val start_datetime: String,
    val venue: String
)

@Entity(tableName = "assignments")
data class AssignmentEntity(
    @PrimaryKey val id: Int,
    val title: String,
    val course_code: String,
    val due_date: String,
    val description: String,
    val is_completed: Boolean = false
)

@Entity(tableName = "announcements")
data class AnnouncementEntity(
    @PrimaryKey val id: Int,
    val title: String,
    val content: String,
    val date: String,
    val author: String
)

@Entity(tableName = "courses")
data class CourseEntity(
    @PrimaryKey val id: Int,
    val code: String,
    val name: String,
    val credit_hours: Int,
    val description: String,
    val resources_url: String?
)

@Entity(tableName = "attendance")
data class AttendanceEntity(
    @PrimaryKey val id: Int,
    val course_code: String,
    val date: String,
    val status: String // Present, Absent, Late
)

@Entity(tableName = "grades")
data class GradeEntity(
    @PrimaryKey val id: Int,
    val course_code: String,
    val grade: String,
    val score: Double,
    val semester: Int,
    val year: Int
)

data class SubmitRequestPayload(
    val student_id: Int,
    val schedule_id: Int,
    val reason: String,
    val preferred_time: String
)

data class GenericResponse(
    val status: String,
    val message: String
)

data class SchedulesResponse(
    val status: String,
    val message: String?,
    val timetable: List<ScheduleDto>?,
    val assessments: List<ScheduleDto>?,
    val class_exceptions: List<ClassExceptionDto>?
)

data class ClassExceptionDto(
    val schedule_id: Int,
    val occurrence_date: String,
    val course_code: String,
    val start_time: String,
    val end_time: String,
    val source_type: String,
    val replacement: String
)

data class ScheduleDto(
    val id: Int,
    val course_code: String?,
    val course_name: String?,
    val day_of_week: String?,
    val start_time: String?,
    val end_time: String?,
    val room_name: String?,
    val type: String?,
    val year_level: Int?,
    val semester: Int?,
    val date: String?
)

data class RequestsResponse(
    val status: String,
    val data: List<RequestDto>?
)

data class RequestDto(
    val id: Int,
    val course_code: String?,
    val status: String?,
    val reason: String?,
    val review_note: String?
)

data class EventsResponse(
    val status: String,
    val data: List<EventDto>?
)

data class EventDto(
    val title: String?,
    val event_type: String?,
    val start_datetime: String?,
    val venue: String?
)

data class AssignmentsResponse(
    val status: String,
    val data: List<AssignmentDto>?
)

data class AssignmentDto(
    val id: Int,
    val title: String?,
    val course_code: String?,
    val due_date: String?,
    val description: String?
)

data class AnnouncementsResponse(
    val status: String,
    val data: List<AnnouncementDto>?
)

data class AnnouncementDto(
    val id: Int,
    val title: String?,
    val content: String?,
    val date: String?,
    val author: String?
)

data class CoursesResponse(
    val status: String,
    val data: List<CourseDto>?
)

data class CourseDto(
    val id: Int,
    val code: String?,
    val name: String?,
    val credit_hours: Int?,
    val description: String?,
    val resources_url: String?
)

data class AttendanceResponse(
    val status: String,
    val data: List<AttendanceDto>?
)

data class AttendanceDto(
    val id: Int,
    val course_code: String?,
    val date: String?,
    val status: String?
)

data class GradesResponse(
    val status: String,
    val data: List<GradeDto>?
)

data class GradeDto(
    val id: Int,
    val course_code: String?,
    val grade: String?,
    val score: Double?,
    val semester: Int?,
    val year: Int?
)
