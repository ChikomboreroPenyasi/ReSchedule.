package com.reschedule.student

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

class SessionManager(context: Context) {
    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()

    private val prefs = EncryptedSharedPreferences.create(
        context,
        "secure_student_session",
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    fun saveAuthToken(token: String) {
        prefs.edit().putString("auth_token", token).apply()
    }

    fun getAuthToken(): String? = prefs.getString("auth_token", null)

    fun saveUser(
        userId: Int,
        programId: Int,
        fullname: String,
        studentNumber: String,
        programmeName: String,
        year: Int,
        semester: Int
    ) {
        prefs.edit()
            .putInt("user_id", userId)
            .putInt("program_id", programId)
            .putString("fullname", fullname)
            .putString("student_number", studentNumber)
            .putString("programme_name", programmeName)
            .putInt("year_level", year)
            .putInt("semester", semester)
            .apply()
    }

    fun getUserId(): Int = prefs.getInt("user_id", 0)
    fun getProgramId(): Int = prefs.getInt("program_id", 0)
    fun getFullname(): String? = prefs.getString("fullname", null)
    fun getStudentNumber(): String? = prefs.getString("student_number", null)
    fun getProgrammeName(): String? = prefs.getString("programme_name", null)
    fun getYearLevel(): Int = prefs.getInt("year_level", 1)
    fun getSemester(): Int = prefs.getInt("semester", 1)

    fun isLoggedIn(): Boolean = getUserId() > 0

    fun logout() {
        prefs.edit().clear().apply()
    }
}
