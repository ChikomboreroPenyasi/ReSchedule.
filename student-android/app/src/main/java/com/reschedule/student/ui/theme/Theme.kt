package com.reschedule.student.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val Navy = Color(15, 35, 66)
val Blue = Color(19, 119, 200)
val Gold = Color(246, 217, 138)
val Muted = Color(95, 111, 136)
val Danger = Color(190, 45, 45)
val Warning = Color(190, 120, 20)
val Ink = Color(23, 32, 51)
val Background = Color(243, 247, 251)

private val LightColorScheme = lightColorScheme(
    primary = Blue,
    onPrimary = Color.White,
    secondary = Navy,
    onSecondary = Color.White,
    tertiary = Gold,
    background = Background,
    surface = Color.White,
    onSurface = Ink,
    onSurfaceVariant = Muted
)

@Composable
fun RescheduleTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColorScheme,
        content = content
    )
}
