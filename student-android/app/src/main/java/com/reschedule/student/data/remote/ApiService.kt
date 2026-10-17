package com.reschedule.student.data.remote

import com.reschedule.student.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    @POST("api_login.php")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("api_schedules.php")
    suspend fun getSchedules(
        @Query("program_id") programId: Int,
        @Header("Authorization") token: String? = null
    ): Response<SchedulesResponse>

    @GET("api_reschedule.php")
    suspend fun getRequests(
        @Query("student_id") studentId: Int,
        @Header("Authorization") token: String? = null
    ): Response<RequestsResponse>

    @GET("api_events.php")
    suspend fun getEvents(
        @Header("Authorization") token: String? = null
    ): Response<EventsResponse>

    @POST("api_reschedule.php")
    suspend fun submitRequest(
        @Body payload: SubmitRequestPayload,
        @Header("Authorization") token: String? = null
    ): Response<GenericResponse>

    @GET("api_assignments.php")
    suspend fun getAssignments(
        @Header("Authorization") token: String? = null
    ): Response<AssignmentsResponse>

    @GET("api_announcements.php")
    suspend fun getAnnouncements(
        @Header("Authorization") token: String? = null
    ): Response<AnnouncementsResponse>

    @GET("api_courses.php")
    suspend fun getCourses(
        @Query("program_id") programId: Int,
        @Query("year_level") yearLevel: Int,
        @Query("semester") semester: Int,
        @Header("Authorization") token: String? = null
    ): Response<CoursesResponse>

    @GET("api_attendance.php")
    suspend fun getAttendance(
        @Header("Authorization") token: String? = null
    ): Response<AttendanceResponse>

    @GET("api_grades.php")
    suspend fun getGrades(
        @Header("Authorization") token: String? = null
    ): Response<GradesResponse>
}
