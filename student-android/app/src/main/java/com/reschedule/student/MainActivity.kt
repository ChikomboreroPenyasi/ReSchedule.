package com.reschedule.student

import android.net.Uri
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.runtime.LaunchedEffect
import com.reschedule.student.ui.AppContent
import com.reschedule.student.ui.MainViewModel
import com.reschedule.student.ui.theme.RescheduleTheme
import kotlinx.coroutines.flow.collectLatest

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        
        setContent {
            RescheduleTheme {
                AppContent(
                    viewModel = viewModel,
                    onOpenUrl = { url ->
                        try {
                            val intent = CustomTabsIntent.Builder().build()
                            intent.launchUrl(this, Uri.parse(url))
                        } catch (e: Exception) {
                            Toast.makeText(this, "Unable to open link", Toast.LENGTH_SHORT).show()
                        }
                    }
                )

                LaunchedEffect(Unit) {
                    viewModel.toastMessage.collectLatest { message ->
                        Toast.makeText(this@MainActivity, message, Toast.LENGTH_LONG).show()
                    }
                }
            }
        }
    }
}
