package com.reschedule.student.data.local

import androidx.room.*
import com.reschedule.student.data.model.*
import kotlinx.coroutines.flow.Flow

@Dao
interface ScheduleDao {
    @Query("SELECT * FROM schedules WHERE year_level = :year AND semester = :semester")
    fun getSchedules(year: Int, semester: Int): Flow<List<ScheduleEntity>>

    @Query("SELECT * FROM schedules")
    fun getAllSchedules(): Flow<List<ScheduleEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(schedules: List<ScheduleEntity>)

    @Query("DELETE FROM schedules")
    suspend fun clearAll()
}

@Dao
interface RequestDao {
    @Query("SELECT * FROM requests")
    fun getRequests(): Flow<List<RequestEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(requests: List<RequestEntity>)

    @Query("DELETE FROM requests")
    suspend fun clearAll()
}

@Dao
interface EventDao {
    @Query("SELECT * FROM events")
    fun getEvents(): Flow<List<EventEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(events: List<EventEntity>)

    @Query("DELETE FROM events")
    suspend fun clearAll()
}

@Dao
interface AssignmentDao {
    @Query("SELECT * FROM assignments")
    fun getAssignments(): Flow<List<AssignmentEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(assignments: List<AssignmentEntity>)

    @Query("UPDATE assignments SET is_completed = :completed WHERE id = :id")
    suspend fun updateStatus(id: Int, completed: Boolean)

    @Query("DELETE FROM assignments")
    suspend fun clearAll()
}

@Dao
interface AnnouncementDao {
    @Query("SELECT * FROM announcements ORDER BY date DESC")
    fun getAnnouncements(): Flow<List<AnnouncementEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(announcements: List<AnnouncementEntity>)

    @Query("DELETE FROM announcements")
    suspend fun clearAll()
}

@Dao
interface CourseDao {
    @Query("SELECT * FROM courses")
    fun getCourses(): Flow<List<CourseEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(courses: List<CourseEntity>)

    @Query("DELETE FROM courses")
    suspend fun clearAll()
}

@Dao
interface AttendanceDao {
    @Query("SELECT * FROM attendance")
    fun getAttendance(): Flow<List<AttendanceEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(attendance: List<AttendanceEntity>)

    @Query("DELETE FROM attendance")
    suspend fun clearAll()
}

@Dao
interface GradeDao {
    @Query("SELECT * FROM grades")
    fun getGrades(): Flow<List<GradeEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(grades: List<GradeEntity>)

    @Query("DELETE FROM grades")
    suspend fun clearAll()
}
