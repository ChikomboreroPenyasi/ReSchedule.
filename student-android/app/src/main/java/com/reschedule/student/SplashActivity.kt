package com.reschedule.student

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.reschedule.student.R
import com.reschedule.student.ui.theme.Gold
import com.reschedule.student.ui.theme.Navy
import com.reschedule.student.ui.theme.RescheduleTheme
import kotlinx.coroutines.delay

class SplashActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            RescheduleTheme {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Navy),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Image(
                            painter = painterResource(R.drawable.reschedule_logo),
                            contentDescription = getString(R.string.app_name),
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 28.dp)
                                .aspectRatio(3.19f),
                            contentScale = ContentScale.Fit,
                            colorFilter = ColorFilter.tint(Color.White)
                        )
                        Text(
                            text = "Student portal",
                            fontSize = 15.sp,
                            color = Gold,
                            modifier = Modifier.padding(top = 4.dp)
                        )
                        Spacer(modifier = Modifier.height(36.dp))
                        CircularProgressIndicator(
                            color = Gold,
                            modifier = Modifier.size(48.dp)
                        )
                    }
                }

                LaunchedEffect(Unit) {
                    delay(1400L)
                    startActivity(Intent(this@SplashActivity, MainActivity::class.java))
                    finish()
                }
            }
        }
    }
}
