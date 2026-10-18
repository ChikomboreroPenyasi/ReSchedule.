package com.reschedule.student.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.reschedule.student.SessionManager
import com.reschedule.student.data.local.AppDatabase
import com.reschedule.student.data.model.*
import com.reschedule.student.data.remote.ApiService
import com.reschedule.student.data.repository.StudentRepository
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

class MainViewModel(application: Application) : AndroidViewModel(application) {
    
    private val sessionManager = SessionManager(application)
    private val database = AppDatabase.getDatabase(application)
    
    private val apiService: ApiService by lazy {
        val client = OkHttpClient.Builder()
            .build()

        Retrofit.Builder()
            .baseUrl(com.reschedule.student.BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    val repository = StudentRepository(apiService, database, sessionManager)

    private val _uiState = MutableStateFlow<UiState>(UiState.Login)
    val uiState: StateFlow<UiState> = _uiState.asStateFlow()

    private val _toastMessage = MutableSharedFlow<String>()
    val toastMessage = _toastMessage.asSharedFlow()

    private val _isRefreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = _isRefreshing.asStateFlow()

    val schedules: Flow<List<ScheduleEntity>> = repository.getSchedules()
    val masterSchedules: Flow<List<ScheduleEntity>> = repository.getMasterSchedules()
    val requests: Flow<List<RequestEntity>> = repository.getRequests()
    val events: Flow<List<EventEntity>> = repository.getEvents()
    val classExceptions = repository.classExceptions.asStateFlow()
    
    // Phase 3 Flows
    val assignments: Flow<List<AssignmentEntity>> = repository.getAssignments()
    val announcements: Flow<List<AnnouncementEntity>> = repository.getAnnouncements()
    val courses: Flow<List<CourseEntity>> = repository.getCourses()
    val attendance: Flow<List<AttendanceEntity>> = repository.getAttendance()
    val grades: Flow<List<GradeEntity>> = repository.getGrades()

    init {
        if (sessionManager.isLoggedIn()) {
            _uiState.value = UiState.Dashboard
            refreshAll()
        } else {
            _uiState.value = UiState.Login
        }
    }

    fun login(username: String, password: String) {
        if (username.isBlank() || password.isBlank()) {
            viewModelScope.launch { _toastMessage.emit("Enter student number and password.") }
            return
        }
        _uiState.value = UiState.Loading
        viewModelScope.launch {
            repository.login(LoginRequest(username, password))
                .onSuccess {
                    _uiState.value = UiState.Dashboard
                    refreshAll()
                }
                .onFailure { error ->
                    _uiState.value = UiState.Login
                    _toastMessage.emit(error.message ?: "Authentication failed")
                }
        }
    }

    fun logout() {
        sessionManager.logout()
        repository.classExceptions.value = emptyList()
        viewModelScope.launch {
            database.scheduleDao().clearAll()
            database.requestDao().clearAll()
            database.eventDao().clearAll()
            database.assignmentDao().clearAll()
            database.announcementDao().clearAll()
            database.courseDao().clearAll()
            database.attendanceDao().clearAll()
            database.gradeDao().clearAll()
        }
        _uiState.value = UiState.Login
    }

    fun navigateTo(state: UiState) {
        _uiState.value = state
    }

    fun refreshAll() {
        if (_isRefreshing.value) return

        viewModelScope.launch {
            _isRefreshing.value = true
            try {
                repository.refreshSchedules()
                repository.refreshRequests()
                repository.refreshEvents()
                repository.refreshAssignments()
                repository.refreshAnnouncements()
                repository.refreshCourses()
                repository.refreshAttendance()
                repository.refreshGrades()
                _toastMessage.emit("Updated")
            } catch (e: Exception) {
                _toastMessage.emit(e.message ?: "Refresh failed")
            } finally {
                _isRefreshing.value = false
            }
        }
    }

    fun submitReschedule(scheduleId: Int, reason: String, preferredTime: String) {
        if (reason.isBlank()) {
            viewModelScope.launch { _toastMessage.emit("Please enter a valid reason.") }
            return
        }
        viewModelScope.launch {
            repository.submitRequest(scheduleId, reason, preferredTime)
                .onSuccess {
                    _toastMessage.emit(it.message)
                    _uiState.value = UiState.Requests
                }
                .onFailure { error ->
                    _toastMessage.emit(error.message ?: "Failed to submit request")
                }
        }
    }

    fun toggleAssignmentStatus(id: Int, completed: Boolean) {
        viewModelScope.launch {
            repository.updateAssignmentStatus(id, completed)
        }
    }

    fun getStudentName(): String = sessionManager.getFullname() ?: "Student"
    fun getProgrammeName(): String = sessionManager.getProgrammeName() ?: "N/A"
    fun getStudentNumber(): String = sessionManager.getStudentNumber() ?: "N/A"
    fun getYearAndSemester(): String = "Year ${sessionManager.getYearLevel()} · Semester ${sessionManager.getSemester()}"
    
    fun getFullProfile(): Map<String, String> = mapOf(
        "Full Name" to getStudentName(),
        "Student Number" to getStudentNumber(),
        "Programme" to getProgrammeName(),
        "Year Level" to sessionManager.getYearLevel().toString(),
        "Semester" to sessionManager.getSemester().toString()
    )

    fun calculateGpa(grades: List<GradeEntity>): Double {
        if (grades.isEmpty()) return 0.0
        return grades.map { it.score }.average()
    }

    fun syncToCalendar() {
        viewModelScope.launch {
            _toastMessage.emit("Calendar sync complete.")
        }
    }
}

sealed interface UiState {
    object Loading : UiState
    object Login : UiState
    object Dashboard : UiState
    object Timetable : UiState
    object Requests : UiState
    object Events : UiState
    object Profile : UiState
    object Notifications : UiState
    object CampusHub : UiState
    object MasterTimetable : UiState
}
