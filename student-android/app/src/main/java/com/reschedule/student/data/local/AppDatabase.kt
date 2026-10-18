package com.reschedule.student.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.migration.Migration
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.sqlite.db.SupportSQLiteDatabase
import com.reschedule.student.data.model.*

@Database(
    entities = [
        ScheduleEntity::class, 
        RequestEntity::class, 
        EventEntity::class,
        AssignmentEntity::class,
        AnnouncementEntity::class,
        CourseEntity::class,
        AttendanceEntity::class,
        GradeEntity::class
    ],
    version = 3,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun scheduleDao(): ScheduleDao
    abstract fun requestDao(): RequestDao
    abstract fun eventDao(): EventDao
    abstract fun assignmentDao(): AssignmentDao
    abstract fun announcementDao(): AnnouncementDao
    abstract fun courseDao(): CourseDao
    abstract fun attendanceDao(): AttendanceDao
    abstract fun gradeDao(): GradeDao

    companion object {
        private val MIGRATION_2_3 = object : Migration(2, 3) {
            override fun migrate(database: SupportSQLiteDatabase) {
                database.execSQL("ALTER TABLE schedules ADD COLUMN date TEXT")
            }
        }

        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "reschedule_db"
                )
                .addMigrations(MIGRATION_2_3)
                .fallbackToDestructiveMigration()
                .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
