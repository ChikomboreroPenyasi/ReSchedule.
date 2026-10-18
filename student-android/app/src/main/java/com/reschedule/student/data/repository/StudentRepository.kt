package com.reschedule.student.data.repository

import com.reschedule.student.SessionManager
import com.reschedule.student.data.local.*
import com.reschedule.student.data.model.*
import com.reschedule.student.data.remote.ApiService
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import retrofit2.Response

class StudentRepository(
    private val api: ApiService,
    private val db: AppDatabase,
    private val session: SessionManager
) {
    val classExceptions = MutableStateFlow<List<ClassExceptionDto>>(emptyList())
    private val scheduleDao = db.scheduleDao()
    private val requestDao = db.requestDao()
    private val eventDao = db.eventDao()
    private val assignmentDao = db.assignmentDao()
    private val announcementDao = db.announcementDao()
    private val courseDao = db.courseDao()
    private val attendanceDao = db.attendanceDao()
    private val gradeDao = db.gradeDao()

    fun getSchedules(): Flow<List<ScheduleEntity>> = 
        scheduleDao.getSchedules(session.getYearLevel(), session.getSemester())

    fun getMasterSchedules(): Flow<List<ScheduleEntity>> = scheduleDao.getAllSchedules()

    fun getRequests(): Flow<List<RequestEntity>> = requestDao.getRequests()
    
    fun getEvents(): Flow<List<EventEntity>> = eventDao.getEvents()

    fun getAssignments(): Flow<List<AssignmentEntity>> = assignmentDao.getAssignments()

    fun getAnnouncements(): Flow<List<AnnouncementEntity>> = announcementDao.getAnnouncements()

    fun getCourses(): Flow<List<CourseEntity>> = courseDao.getCourses()

    fun getAttendance(): Flow<List<AttendanceEntity>> = attendanceDao.getAttendance()

    fun getGrades(): Flow<List<GradeEntity>> = gradeDao.getGrades()

    suspend fun refreshSchedules(): Result<Unit> {
        return try {
            val response = api.getSchedules(session.getProgramId(), session.getAuthToken())
            if (response.isSuccessful) {
                val body = response.body()
                classExceptions.value = body?.class_exceptions.orEmpty()
                val entities = mutableListOf<ScheduleEntity>()
                body?.timetable?.forEach { dto ->
                    entities.add(dto.toEntity("Class"))
                }
                body?.assessments?.forEach { dto ->
                    entities.add(dto.toEntity(dto.type ?: "Assessment"))
                }
                scheduleDao.clearAll()
                scheduleDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            classExceptions.value = emptyList()
            Result.failure(e)
        }
    }

    suspend fun refreshRequests(): Result<Unit> {
        return try {
            val response = api.getRequests(session.getUserId(), session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                requestDao.clearAll()
                requestDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshEvents(): Result<Unit> {
        return try {
            val response = api.getEvents(session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                eventDao.clearAll()
                eventDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshAssignments(): Result<Unit> {
        return try {
            val response = api.getAssignments(session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                assignmentDao.clearAll()
                assignmentDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshAnnouncements(): Result<Unit> {
        return try {
            val response = api.getAnnouncements(session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                announcementDao.clearAll()
                announcementDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshCourses(): Result<Unit> {
        return try {
            val programmeId = session.getProgramId()
            val yearLevel = session.getYearLevel()
            val semester = session.getSemester()
            val response = api.getCourses(programmeId, yearLevel, semester, session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                courseDao.clearAll()
                courseDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshAttendance(): Result<Unit> {
        return try {
            val response = api.getAttendance(session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                attendanceDao.clearAll()
                attendanceDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshGrades(): Result<Unit> {
        return try {
            val response = api.getGrades(session.getAuthToken())
            if (response.isSuccessful) {
                val entities = response.body()?.data?.map { it.toEntity() } ?: emptyList()
                gradeDao.clearAll()
                gradeDao.insertAll(entities)
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun login(request: LoginRequest): Result<LoginResponse> {
        return try {
            val response = api.login(request)
            if (response.isSuccessful && response.body()?.status == "success") {
                val body = response.body()!!
                session.saveAuthToken(body.token ?: "")
                body.user?.let {
                    session.saveUser(
                        it.id,
                        it.program_id,
                        it.fullname,
                        it.username,
                        it.programme_name,
                        it.year?.toIntOrNull() ?: 1,
                        it.semester?.toIntOrNull() ?: 1
                    )
                }
                Result.success(body)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun submitRequest(scheduleId: Int, reason: String, preferredTime: String): Result<GenericResponse> {
        return try {
            val payload = SubmitRequestPayload(session.getUserId(), scheduleId, reason, preferredTime)
            val response = api.submitRequest(payload, session.getAuthToken())
            if (response.isSuccessful && response.body()?.status == "success") {
                refreshRequests()
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to submit request"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateAssignmentStatus(id: Int, completed: Boolean) {
        assignmentDao.updateStatus(id, completed)
    }

    private fun ScheduleDto.toEntity(defaultType: String) = ScheduleEntity(
        id = id,
        course_code = course_code ?: "",
        course_name = course_name ?: "",
        day_of_week = day_of_week ?: "",
        start_time = start_time ?: "",
        end_time = end_time ?: "",
        room_name = room_name ?: "",
        type = type ?: defaultType,
        year_level = year_level ?: 1,
        semester = semester ?: 1,
        date = date
    )

    private fun RequestDto.toEntity() = RequestEntity(
        id = id,
        course_code = course_code ?: "",
        status = status ?: "Pending",
        reason = reason ?: "",
        review_note = review_note
    )

    private fun EventDto.toEntity() = EventEntity(
        title = title ?: "",
        event_type = event_type ?: "Other",
        start_datetime = start_datetime ?: "",
        venue = venue ?: ""
    )

    private fun AssignmentDto.toEntity() = AssignmentEntity(
        id = id,
        title = title ?: "",
        course_code = course_code ?: "",
        due_date = due_date ?: "",
        description = description ?: ""
    )

    private fun AnnouncementDto.toEntity() = AnnouncementEntity(
        id = id,
        title = title ?: "",
        content = content ?: "",
        date = date ?: "",
        author = author ?: "System"
    )

    private fun CourseDto.toEntity() = CourseEntity(
        id = id,
        code = code ?: "",
        name = name ?: "",
        credit_hours = credit_hours ?: 0,
        description = description ?: "",
        resources_url = resources_url
    )

    private fun AttendanceDto.toEntity() = AttendanceEntity(
        id = id,
        course_code = course_code ?: "",
        date = date ?: "",
        status = status ?: "Absent"
    )

    private fun GradeDto.toEntity() = GradeEntity(
        id = id,
        course_code = course_code ?: "",
        grade = grade ?: "N/A",
        score = score ?: 0.0,
        semester = semester ?: 1,
        year = year ?: 1
    )
}
