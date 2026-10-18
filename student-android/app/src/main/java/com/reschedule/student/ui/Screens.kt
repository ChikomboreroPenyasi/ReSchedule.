package com.reschedule.student.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.reschedule.student.data.model.*
import com.reschedule.student.ui.theme.*
import java.text.SimpleDateFormat
import java.util.*

@Composable
fun AppContent(viewModel: MainViewModel, onOpenUrl: (String) -> Unit) {
    val state by viewModel.uiState.collectAsState()

    Box(modifier = Modifier.fillMaxSize().background(Background)) {
        when (state) {
            UiState.Loading -> {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.Center), color = Blue)
            }
            UiState.Login -> {
                LoginScreen(viewModel = viewModel, onOpenUrl = onOpenUrl)
            }
            UiState.Dashboard -> {
                DashboardScreen(viewModel = viewModel)
            }
            UiState.Timetable -> {
                TimetableScreen(viewModel = viewModel)
            }
            UiState.Requests -> {
                RequestsScreen(viewModel = viewModel)
            }
            UiState.Events -> {
                EventsScreen(viewModel = viewModel)
            }
            UiState.Profile -> {
                ProfileScreen(viewModel = viewModel)
            }
            UiState.Notifications -> {
                NotificationsScreen(viewModel = viewModel)
            }
            UiState.CampusHub -> {
                CampusHubScreen(viewModel = viewModel)
            }
            UiState.MasterTimetable -> {
                MasterTimetableScreen(viewModel = viewModel)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun Header(title: String, activeState: UiState, viewModel: MainViewModel) {
    TopAppBar(
        title = { Text(title, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 20.sp) },
        colors = TopAppBarDefaults.topAppBarColors(containerColor = Navy),
        actions = {
            if (activeState != UiState.Login) {
                IconButton(onClick = { viewModel.navigateTo(UiState.CampusHub) }) {
                    Icon(Icons.Default.Home, contentDescription = "Campus Hub", tint = Gold)
                }
                IconButton(onClick = { viewModel.navigateTo(UiState.Notifications) }) {
                    Icon(Icons.Default.Notifications, contentDescription = "Notifications", tint = Gold)
                }
                IconButton(onClick = { viewModel.navigateTo(UiState.Profile) }) {
                    Icon(Icons.Default.Person, contentDescription = "Profile", tint = Gold)
                }
            }
        }
    )
}

@Composable
fun NavigationTabs(activeState: UiState, onTabSelected: (UiState) -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceAround
    ) {
        val tabs = listOf(
            Triple("Dashboard", UiState.Dashboard, Blue),
            Triple("Timetable", UiState.Timetable, Blue),
            Triple("Requests", UiState.Requests, Blue),
            Triple("Events", UiState.Events, Blue)
        )
        tabs.forEach { (label, state, color) ->
            Text(
                text = label,
                color = if (activeState == state) Navy else color,
                fontWeight = if (activeState == state) FontWeight.Bold else FontWeight.Medium,
                fontSize = 13.sp,
                modifier = Modifier.clickable { onTabSelected(state) }.padding(4.dp)
            )
        }
    }
}

@Composable
fun LoginScreen(viewModel: MainViewModel, onOpenUrl: (String) -> Unit) {
    var studentNumber by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        verticalArrangement = Arrangement.Top
    ) {
        Spacer(modifier = Modifier.height(40.dp))
        Text("Reschedule.", color = Navy, fontSize = 32.sp, fontWeight = FontWeight.Bold)
        Text("Student portal", color = Blue, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        Spacer(modifier = Modifier.height(28.dp))
        Text("Welcome back", color = Navy, fontSize = 27.sp, fontWeight = FontWeight.Bold)
        Text(
            "Sign in to view your timetable, assessments, events, and requests.",
            color = Muted,
            fontSize = 15.sp,
            modifier = Modifier.padding(bottom = 28.dp)
        )

        OutlinedTextField(
            value = studentNumber,
            onValueChange = { studentNumber = it },
            label = { Text("Student number") },
            modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Blue,
                focusedContainerColor = Color.White,
                unfocusedContainerColor = Color.White
            ),
            singleLine = true
        )

        OutlinedTextField(
            value = password,
            onValueChange = { password = it },
            label = { Text("Password") },
            modifier = Modifier.fillMaxWidth().padding(bottom = 18.dp),
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Blue,
                focusedContainerColor = Color.White,
                unfocusedContainerColor = Color.White
            ),
            singleLine = true
        )

        Button(
            onClick = { viewModel.login(studentNumber, password) },
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = Blue)
        ) {
            Text("Log in", color = Color.White, fontSize = 15.sp)
        }

        Spacer(modifier = Modifier.height(16.dp))

        Text(
            text = "New student? Create an account on the website",
            color = Blue,
            fontSize = 14.sp,
            modifier = Modifier
                .align(Alignment.CenterHorizontally)
                .clickable { onOpenUrl(com.reschedule.student.BuildConfig.SIGNUP_URL) }
                .padding(12.dp)
        )
    }
}

@Composable
fun DashboardScreen(viewModel: MainViewModel) {
    val schedules by viewModel.schedules.collectAsState(initial = emptyList())
    val requests by viewModel.requests.collectAsState(initial = emptyList())
    val events by viewModel.events.collectAsState(initial = emptyList())

    val todayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date())
    val todayClassesCount = schedules.count { it.day_of_week.equals(todayName, ignoreCase = true) }
    val assessmentsCount = schedules.count { !it.type.equals("class", ignoreCase = true) }

    Scaffold(
        topBar = { Header("Reschedule", UiState.Dashboard, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Text("Hello, ${viewModel.getStudentName()}", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text(viewModel.getProgrammeName(), color = Muted, fontSize = 14.sp)
            Spacer(modifier = Modifier.height(16.dp))

            NavigationTabs(UiState.Dashboard, onTabSelected = { viewModel.navigateTo(it) })

            SectionTitle("Summary")
            Row(modifier = Modifier.fillMaxWidth()) {
                Box(modifier = Modifier.weight(1f)) {
                    SummaryCard("Today", todayClassesCount, "class slots")
                }
                Spacer(modifier = Modifier.width(8.dp))
                Box(modifier = Modifier.weight(1f)) {
                    SummaryCard("Assessments", assessmentsCount, "published slots")
                }
            }
            Row(modifier = Modifier.fillMaxWidth()) {
                Box(modifier = Modifier.weight(1f)) {
                    SummaryCard("Requests", requests.size, "submitted")
                }
                Spacer(modifier = Modifier.width(8.dp))
                Box(modifier = Modifier.weight(1f)) {
                    SummaryCard("Events", events.size, "upcoming")
                }
            }

            SectionTitle("Next class")
            val nextClass = schedules.firstOrNull { it.type.equals("class", ignoreCase = true) }
            if (nextClass != null) {
                ScheduleCard(nextClass, showRequest = true, onRequestClick = { viewModel.navigateTo(UiState.Requests) })
            } else {
                EmptyStateText("No class timetable published yet.")
            }

            SectionTitle("Recent notifications")
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    if (requests.isEmpty() && events.isEmpty()) {
                        EmptyStateText("You are up to date.")
                    } else {
                        requests.take(2).forEach {
                            Text("• ${it.course_code} request: ${it.status}", color = Warning, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                        }
                        events.take(2).forEach {
                            Text("• ${it.title} · ${it.start_datetime}", color = Blue, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                        }
                        Text(
                            "View all",
                            color = Blue,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.clickable { viewModel.navigateTo(UiState.Notifications) }.padding(top = 8.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun TimetableScreen(viewModel: MainViewModel) {
    val schedules by viewModel.schedules.collectAsState(initial = emptyList())
    val classExceptions by viewModel.classExceptions.collectAsState()
    var searchQuery by remember { mutableStateOf("") }
    var selectedTypeIndex by remember { mutableStateOf(0) }
    val types = listOf("All entries", "Classes", "Assessments")
    var expanded by remember { mutableStateOf(false) }

    Scaffold(
        topBar = { Header("Timetable", UiState.Timetable, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(20.dp)
        ) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text("Timetable", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
                    Text(viewModel.getStudentName(), color = Muted, fontSize = 14.sp)
                }
                Text(
                    "View Master",
                    color = Blue,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.clickable { viewModel.navigateTo(UiState.MasterTimetable) }.padding(8.dp)
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))

            NavigationTabs(UiState.Timetable, onTabSelected = { viewModel.navigateTo(it) })

            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                label = { Text("Search course, day, or venue") },
                modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Blue,
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White
                ),
                singleLine = true
            )

            Box(modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp)) {
                OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
                    Text(types[selectedTypeIndex])
                }
                DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                    types.forEachIndexed { index, type ->
                        DropdownMenuItem(
                            text = { Text(type) },
                            onClick = {
                                selectedTypeIndex = index
                                expanded = false
                            }
                        )
                    }
                }
            }

            SectionTitle("Aligned with your Year/Semester")

            if (classExceptions.isNotEmpty()) {
                SectionTitle("One-time class replacements")
                classExceptions.forEach { exception ->
                    Text(
                        "${exception.occurrence_date}: ${exception.course_code} (${exception.start_time}-${exception.end_time}) replaced by ${exception.replacement}",
                        color = Warning,
                        fontSize = 13.sp,
                        modifier = Modifier.padding(bottom = 8.dp)
                    )
                }
            }

            val filtered = schedules.filter { item ->
                val searchMatch = searchQuery.isBlank() || 
                        item.course_code.contains(searchQuery, ignoreCase = true) ||
                        item.course_name.contains(searchQuery, ignoreCase = true) ||
                        item.day_of_week.contains(searchQuery, ignoreCase = true) ||
                        item.room_name.contains(searchQuery, ignoreCase = true)

                val typeMatch = when (selectedTypeIndex) {
                    1 -> item.type.equals("class", ignoreCase = true)
                    2 -> !item.type.equals("class", ignoreCase = true)
                    else -> true
                }
                searchMatch && typeMatch
            }

            if (filtered.isEmpty()) {
                EmptyStateText("No matching timetable entries.")
            } else {
                Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                    filtered.forEach { item ->
                        ScheduleCard(item, showRequest = true, onRequestClick = { viewModel.navigateTo(UiState.Requests) })
                    }
                }
            }
        }
    }
}

@Composable
fun MasterTimetableScreen(viewModel: MainViewModel) {
    val schedules by viewModel.masterSchedules.collectAsState(initial = emptyList())
    var searchQuery by remember { mutableStateOf("") }
    
    Scaffold(
        topBar = { Header("Master Timetable", UiState.MasterTimetable, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(20.dp)
        ) {
            Text("Master Timetable", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text("All courses for this program", color = Muted, fontSize = 14.sp, modifier = Modifier.padding(bottom = 16.dp))

            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                label = { Text("Search any course, building or building code") },
                modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Blue,
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White
                ),
                singleLine = true
            )

            SectionTitle("Comprehensive List")

            val filtered = schedules.filter { item ->
                searchQuery.isBlank() || 
                        item.course_code.contains(searchQuery, ignoreCase = true) ||
                        item.course_name.contains(searchQuery, ignoreCase = true) ||
                        item.day_of_week.contains(searchQuery, ignoreCase = true) ||
                        item.room_name.contains(searchQuery, ignoreCase = true)
            }

            if (filtered.isEmpty()) {
                EmptyStateText("No timetable entries found.")
            } else {
                Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                    filtered.forEach { item ->
                        ScheduleCard(item, showRequest = false, onRequestClick = {})
                        Text(
                            "Year ${item.year_level} · Semester ${item.semester}",
                            color = Muted,
                            fontSize = 11.sp,
                            modifier = Modifier.padding(start = 16.dp, bottom = 12.dp)
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                "Back to My Timetable",
                color = Blue,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.clickable { viewModel.navigateTo(UiState.Timetable) }
            )
        }
    }
}

@Composable
fun RequestsScreen(viewModel: MainViewModel) {
    val schedules by viewModel.schedules.collectAsState(initial = emptyList())
    val requests by viewModel.requests.collectAsState(initial = emptyList())

    var expanded by remember { mutableStateOf(false) }
    var selectedScheduleIndex by remember { mutableStateOf(-1) }
    var reason by remember { mutableStateOf("") }
    var preferredTime by remember { mutableStateOf("") }

    Scaffold(
        topBar = { Header("My requests", UiState.Requests, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Text("My requests", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text(viewModel.getStudentName(), color = Muted, fontSize = 14.sp, modifier = Modifier.padding(bottom = 16.dp))

            NavigationTabs(UiState.Requests, onTabSelected = { viewModel.navigateTo(it) })

            SectionTitle("Submit a reschedule request")
            Card(
                modifier = Modifier.fillMaxWidth().padding(bottom = 16.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text("Select timetable slot", color = Muted, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Box(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                        val displayText = if (selectedScheduleIndex in schedules.indices) {
                            val s = schedules[selectedScheduleIndex]
                            "#${s.id} · ${s.course_code} · ${s.day_of_week} ${s.start_time}"
                        } else {
                            "Choose a slot..."
                        }
                        OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
                            Text(displayText)
                        }
                        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                            schedules.forEachIndexed { index, s ->
                                DropdownMenuItem(
                                    text = { Text("#${s.id} · ${s.course_code} · ${s.day_of_week} ${s.start_time}") },
                                    onClick = {
                                        selectedScheduleIndex = index
                                        expanded = false
                                    }
                                )
                            }
                        }
                    }

                    OutlinedTextField(
                        value = reason,
                        onValueChange = { reason = it },
                        label = { Text("Reason for reschedule") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Blue,
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White
                        )
                    )

                    OutlinedTextField(
                        value = preferredTime,
                        onValueChange = { preferredTime = it },
                        label = { Text("Preferred time (optional)") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Blue,
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White
                        )
                    )

                    Button(
                        onClick = {
                            if (selectedScheduleIndex in schedules.indices) {
                                viewModel.submitReschedule(schedules[selectedScheduleIndex].id, reason, preferredTime)
                                reason = ""
                                preferredTime = ""
                                selectedScheduleIndex = -1
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = Blue)
                    ) {
                        Text("Submit request")
                    }
                }
            }

            SectionTitle("Request history")
            if (requests.isEmpty()) {
                EmptyStateText("You have not submitted any requests.")
            } else {
                requests.forEach { item ->
                    Card(
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White)
                    ) {
                        Column(modifier = Modifier.padding(14.dp)) {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text(item.course_code, color = Ink, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                Text(item.status, color = if (item.status.contains("Approved", ignoreCase = true)) Blue else Warning, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                            }
                            Text(item.reason, color = Ink, fontSize = 14.sp, modifier = Modifier.padding(top = 4.dp))
                            val note = item.review_note ?: "No reviewer note yet."
                            Text("Reviewer: $note", color = Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 6.dp))
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun EventsScreen(viewModel: MainViewModel) {
    val events by viewModel.events.collectAsState(initial = emptyList())

    Scaffold(
        topBar = { Header("Events", UiState.Events, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Text("Events", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text(viewModel.getStudentName(), color = Muted, fontSize = 14.sp, modifier = Modifier.padding(bottom = 16.dp))

            NavigationTabs(UiState.Events, onTabSelected = { viewModel.navigateTo(it) })

            SectionTitle("Academic calendar")
            CalendarView(events = events)

            SectionTitle("Upcoming events")
            if (events.isEmpty()) {
                EmptyStateText("No upcoming events published.")
            } else {
                events.forEach { item ->
                    Card(
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White)
                    ) {
                        Column(modifier = Modifier.padding(14.dp)) {
                            Text(item.title, color = Ink, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                            Text("${item.event_type} · ${item.start_datetime}", color = Muted, fontSize = 14.sp, modifier = Modifier.padding(top = 4.dp))
                            Text(item.venue, color = Navy, fontWeight = FontWeight.Bold, fontSize = 14.sp, modifier = Modifier.padding(top = 4.dp))
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ProfileScreen(viewModel: MainViewModel) {
    val profile = viewModel.getFullProfile()

    Scaffold(
        topBar = { Header("My Profile", UiState.Profile, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Text("Student Profile", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text("Manage your account details", color = Muted, fontSize = 14.sp, modifier = Modifier.padding(bottom = 16.dp))

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    profile.forEach { (key, value) ->
                        Text(key, color = Muted, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        Text(value, color = Ink, fontSize = 16.sp, modifier = Modifier.padding(bottom = 12.dp))
                        HorizontalDivider(color = Background, thickness = 1.dp, modifier = Modifier.padding(bottom = 12.dp))
                    }

                    Button(
                        onClick = { viewModel.syncToCalendar() },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = Blue)
                    ) {
                        Text("Sync to Device Calendar", color = Color.White)
                    }

                    Spacer(modifier = Modifier.height(12.dp))

                    OutlinedButton(
                        onClick = { viewModel.logout() },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Danger)
                    ) {
                        Text("Log out")
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            Text(
                "Go back to dashboard",
                color = Blue,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.clickable { viewModel.navigateTo(UiState.Dashboard) }
            )
        }
    }
}

@Composable
fun NotificationsScreen(viewModel: MainViewModel) {
    val requests by viewModel.requests.collectAsState(initial = emptyList())
    val events by viewModel.events.collectAsState(initial = emptyList())

    Scaffold(
        topBar = { Header("Notifications", UiState.Notifications, viewModel) }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Text("Notifications", color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text("Stay updated with recent activities", color = Muted, fontSize = 14.sp, modifier = Modifier.padding(bottom = 16.dp))

            SectionTitle("Requests Updates")
            if (requests.isEmpty()) {
                EmptyStateText("No request updates.")
            } else {
                requests.forEach {
                    NoticeItem(text = "${it.course_code} request status: ${it.status}", color = Warning)
                }
            }

            SectionTitle("Upcoming Events")
            if (events.isEmpty()) {
                EmptyStateText("No upcoming events.")
            } else {
                events.forEach {
                    NoticeItem(text = "${it.title} starts at ${it.start_datetime}", color = Blue)
                }
            }

            Spacer(modifier = Modifier.height(20.dp))
            Text(
                "Go back to dashboard",
                color = Blue,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.clickable { viewModel.navigateTo(UiState.Dashboard) }
            )
        }
    }
}

@Composable
fun CampusHubScreen(viewModel: MainViewModel) {
    var activeSection by remember { mutableIntStateOf(-1) }
    val schedules by viewModel.schedules.collectAsState(initial = emptyList())
    val courses by viewModel.courses.collectAsState(initial = emptyList())
    val announcements by viewModel.announcements.collectAsState(initial = emptyList())
    val todayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date())
    val todayClasses = schedules
        .filter { it.type.equals("class", ignoreCase = true) && it.day_of_week.equals(todayName, ignoreCase = true) }
        .sortedBy { it.start_time }
    val nextClass = todayClasses.firstOrNull()

    Scaffold(
        topBar = { Header("Student Home", UiState.CampusHub, viewModel) }
    ) { paddingValues ->
        Column(modifier = Modifier.fillMaxSize().padding(paddingValues)) {
            if (activeSection == -1) {
                Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp)) {
                    Text(
                        SimpleDateFormat("EEEE, d MMMM", Locale.getDefault()).format(Date()),
                        color = Blue,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Card(
                        modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                        colors = CardDefaults.cardColors(containerColor = Navy)
                    ) {
                        Column(modifier = Modifier.fillMaxWidth().padding(20.dp)) {
                            Text("Welcome back", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            Text(viewModel.getStudentName(), color = Color.White, fontSize = 25.sp, fontWeight = FontWeight.Bold)
                            Text(viewModel.getProgrammeName(), color = Color.White.copy(alpha = 0.82f), fontSize = 14.sp, modifier = Modifier.padding(top = 5.dp))
                            Text(viewModel.getYearAndSemester(), color = Gold, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(top = 8.dp))
                        }
                    }

                    Row(modifier = Modifier.fillMaxWidth().padding(top = 14.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(
                            "Today" to todayClasses.size.toString(),
                            "Courses" to courses.size.toString(),
                            "Notices" to announcements.size.toString()
                        ).forEach { (label, value) ->
                            Card(modifier = Modifier.weight(1f), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                                Column(modifier = Modifier.fillMaxWidth().padding(11.dp)) {
                                    Text(label, color = Muted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    Text(value, color = Navy, fontSize = 22.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth().padding(top = 20.dp, bottom = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Next up", color = Navy, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Text("My timetable", color = Blue, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                            modifier = Modifier.clickable { viewModel.navigateTo(UiState.Timetable) }.padding(4.dp))
                    }

                    Card(modifier = Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            if (nextClass == null) {
                                Text("No more classes today", color = Navy, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                                Text("Your timetable and campus updates will appear here.", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 4.dp))
                            } else {
                                Text("${nextClass.start_time.take(5)} - ${nextClass.end_time.take(5)}", color = Blue, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                Text(nextClass.course_code, color = Navy, fontSize = 18.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 4.dp))
                                Text(nextClass.course_name, color = Ink, fontSize = 14.sp)
                                Text("${nextClass.day_of_week} · ${nextClass.room_name}", color = Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 4.dp))
                            }
                        }
                    }

                    Text("Your pages", color = Navy, fontSize = 19.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 20.dp, bottom = 8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        listOf("Course Pages" to 0, "Announcements" to 1).forEach { (title, section) ->
                            Card(
                                modifier = Modifier.weight(1f).clickable { activeSection = section },
                                colors = CardDefaults.cardColors(containerColor = Color.White)
                            ) {
                                Column(modifier = Modifier.fillMaxWidth().padding(14.dp)) {
                                    Text(title, color = Navy, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    Text(if (section == 0) "Courses and resources" else "Campus updates", color = Muted, fontSize = 11.sp, modifier = Modifier.padding(top = 4.dp))
                                    Text("Open →", color = Blue, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 9.dp))
                                }
                            }
                        }
                    }

                    announcements.firstOrNull()?.let { item ->
                        Text("Latest announcement", color = Navy, fontSize = 19.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 20.dp, bottom = 8.dp))
                        Card(modifier = Modifier.fillMaxWidth().clickable { activeSection = 1 }, colors = CardDefaults.cardColors(containerColor = Color.White)) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text(item.title, color = Navy, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                Text(item.content, color = Muted, fontSize = 13.sp, maxLines = 3, modifier = Modifier.padding(top = 5.dp))
                                Text("${item.author} · ${item.date}", color = Blue, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(top = 8.dp))
                            }
                        }
                    }
                }
            } else {
                Column(modifier = Modifier.fillMaxSize()) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("‹ Home", color = Blue, fontWeight = FontWeight.Bold, modifier = Modifier.clickable { activeSection = -1 }.padding(8.dp))
                        Text(viewModel.getYearAndSemester(), color = Muted, fontSize = 12.sp)
                    }
                    TabRow(selectedTabIndex = activeSection, containerColor = Navy, contentColor = Gold) {
                        listOf("Course Pages", "Announcements").forEachIndexed { index, title ->
                            Tab(selected = activeSection == index, onClick = { activeSection = index }, text = {
                                Text(title, color = if (activeSection == index) Gold else Color.White)
                            })
                        }
                    }
                    Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp)) {
                        if (activeSection == 0) CoursePagesSection(viewModel) else AnnouncementsSection(viewModel)
                    }
                }
            }
        }
    }
}

@Composable
fun CoursePagesSection(viewModel: MainViewModel) {
    val courses by viewModel.courses.collectAsState(initial = emptyList())
    val attendance by viewModel.attendance.collectAsState(initial = emptyList())

    Text("My Courses & Resources", color = Navy, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    Spacer(modifier = Modifier.height(12.dp))

    if (courses.isEmpty()) {
        EmptyStateText("No courses found.")
    } else {
        courses.forEach { course ->
            val courseAttendance = attendance.filter { it.course_code == course.code }
            val presentCount = courseAttendance.count { it.status == "Present" }
            val totalClasses = courseAttendance.size.coerceAtLeast(1)
            val attendancePercent = (presentCount * 100) / totalClasses

            Card(
                modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text(course.code, color = Blue, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                    Text(course.name, color = Ink, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                    Text("Attendance: $attendancePercent% · Credit Hours: ${course.credit_hours}", color = Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 4.dp))
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("Description:", color = Ink, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text(course.description, color = Muted, fontSize = 12.sp)
                    
                    if (!course.resources_url.isNullOrBlank()) {
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("View Resources ->", color = Blue, fontSize = 12.sp, fontWeight = FontWeight.Bold, modifier = Modifier.clickable { })
                    }
                }
            }
        }
    }
}

@Composable
fun PlannerTasksSection(viewModel: MainViewModel) {
    val assignments by viewModel.assignments.collectAsState(initial = emptyList())

    Text("Study Planner & Assignments", color = Navy, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    Spacer(modifier = Modifier.height(12.dp))

    Text("Pending Deadlines", color = Ink, fontSize = 14.sp, fontWeight = FontWeight.Bold)
    
    if (assignments.isEmpty()) {
        EmptyStateText("No pending assignments.")
    } else {
        assignments.forEach { item ->
            Card(
                modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(
                        checked = item.is_completed,
                        onCheckedChange = { viewModel.toggleAssignmentStatus(item.id, it) }
                    )
                    Column(modifier = Modifier.padding(start = 8.dp)) {
                        Text(item.title, color = Ink, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        Text("${item.course_code} · Due: ${item.due_date}", color = Warning, fontSize = 12.sp)
                    }
                }
            }
        }
    }
}

@Composable
fun GpaCalculatorSection(viewModel: MainViewModel) {
    val grades by viewModel.grades.collectAsState(initial = emptyList())
    val gpa = viewModel.calculateGpa(grades)

    Text("GPA & Performance Calculator", color = Navy, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    Spacer(modifier = Modifier.height(12.dp))

    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Current Cumulative GPA", color = Muted, fontSize = 13.sp)
            Text(String.format(Locale.US, "%.2f / 4.00", gpa), color = Navy, fontSize = 32.sp, fontWeight = FontWeight.Bold)
            HorizontalDivider(color = Background, modifier = Modifier.padding(vertical = 12.dp))
            
            Text("Recent Grades", color = Ink, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            if (grades.isEmpty()) {
                Text("No grade data available.", color = Muted, fontSize = 12.sp)
            } else {
                grades.forEach { grade ->
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(grade.course_code, fontSize = 14.sp)
                        Text(grade.grade, color = Blue, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }
                }
            }
        }
    }
}

@Composable
fun CampusMapSection() {
    Text("Campus Navigation & Rooms", color = Navy, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    Spacer(modifier = Modifier.height(12.dp))

    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Interactive Room Guide", color = Blue, fontWeight = FontWeight.Bold, fontSize = 15.sp)
            Text("Search any auditorium, lab or faculty building for instantaneous indoor routing map details.", color = Ink, fontSize = 13.sp)
            Spacer(modifier = Modifier.height(12.dp))
            OutlinedTextField(
                value = "",
                onValueChange = {},
                placeholder = { Text("e.g. Engineering Block Room 402") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )
            Spacer(modifier = Modifier.height(12.dp))
            Text("Suggested Locations:", color = Muted, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            listOf("Central Library", "Main Cafeteria", "Auditorium A", "Science Lab 101").forEach {
                Text("• $it", color = Blue, fontSize = 12.sp, modifier = Modifier.padding(vertical = 2.dp).clickable { })
            }
        }
    }
}

@Composable
fun AnnouncementsSection(viewModel: MainViewModel) {
    val announcements by viewModel.announcements.collectAsState(initial = emptyList())

    Text("University Announcements", color = Navy, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    Spacer(modifier = Modifier.height(12.dp))

    if (announcements.isEmpty()) {
        EmptyStateText("No announcements available.")
    } else {
        announcements.forEach { item ->
            Card(
                modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text(item.title, color = Ink, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    Text(item.content, color = Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 4.dp))
                    Spacer(modifier = Modifier.height(6.dp))
                    Text("${item.author} · ${item.date}", color = Blue, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
fun NoticeItem(text: String, color: Color) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(modifier = Modifier.size(8.dp).background(color, shape = MaterialTheme.shapes.small))
            Spacer(modifier = Modifier.width(12.dp))
            Text(text, color = Ink, fontSize = 14.sp)
        }
    }
}

@Composable
fun CalendarView(events: List<EventEntity>) {
    val calendar = Calendar.getInstance()
    val monthTitle = SimpleDateFormat("MMMM yyyy", Locale.getDefault()).format(calendar.time)

    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Text(monthTitle, color = Blue, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(bottom = 8.dp))

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceAround) {
                listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun").forEach { day ->
                    Text(day, color = Muted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(4.dp))

            val firstDay = calendar.clone() as Calendar
            firstDay.set(Calendar.DAY_OF_MONTH, 1)
            val offset = (firstDay.get(Calendar.DAY_OF_WEEK) + 5) % 7
            val daysInMonth = calendar.getActualMaximum(Calendar.DAY_OF_MONTH)

            var currentDay = 1
            for (week in 0..5) {
                if (currentDay > daysInMonth) break
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceAround) {
                    for (dayOfWeek in 0..6) {
                        if ((week == 0 && dayOfWeek < offset) || currentDay > daysInMonth) {
                            Box(modifier = Modifier.size(36.dp))
                        } else {
                            val dateKey = String.format(Locale.US, "%04d-%02d-%02d", calendar.get(Calendar.YEAR), calendar.get(Calendar.MONTH) + 1, currentDay)
                            val hasEvent = events.any { it.start_datetime.startsWith(dateKey) }

                            Box(
                                modifier = Modifier
                                    .size(36.dp)
                                    .background(if (hasEvent) Color(231, 243, 255) else Color.Transparent)
                                    .padding(4.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = currentDay.toString(),
                                    color = if (hasEvent) Blue else Ink,
                                    fontSize = 12.sp,
                                    fontWeight = if (hasEvent) FontWeight.Bold else FontWeight.Normal
                                )
                            }
                            currentDay++
                        }
                    }
                }
                Spacer(modifier = Modifier.height(4.dp))
            }
        }
    }
}

@Composable
fun SectionTitle(title: String) {
    Text(
        text = title,
        color = Navy,
        fontSize = 19.sp,
        fontWeight = FontWeight.Bold,
        modifier = Modifier.padding(top = 14.dp, bottom = 8.dp)
    )
}

@Composable
fun EmptyStateText(text: String) {
    Text(
        text = text,
        color = Muted,
        fontSize = 14.sp,
        modifier = Modifier.padding(bottom = 10.dp)
    )
}

@Composable
fun SummaryCard(title: String, value: Int, detail: String) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Text(title, color = Muted, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            Text(value.toString(), color = Navy, fontSize = 25.sp, fontWeight = FontWeight.Bold)
            Text(detail, color = Muted, fontSize = 12.sp)
        }
    }
}

@Composable
fun ScheduleCard(item: ScheduleEntity, showRequest: Boolean, onRequestClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(item.course_code, color = Blue, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Text(item.course_name, color = Ink, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 4.dp))
            Text("Schedule #${item.id}", color = Muted, fontSize = 11.sp, modifier = Modifier.padding(top = 4.dp))
            Text("${item.day_of_week} · ${item.start_time} - ${item.end_time}", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 6.dp))
            Text(item.room_name, color = Navy, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 4.dp))
            
            if (!item.type.equals("class", ignoreCase = true)) {
                Text(item.type, color = Warning, fontSize = 12.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 5.dp))
            }

            if (showRequest) {
                Button(
                    onClick = onRequestClick,
                    modifier = Modifier.padding(top = 8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Blue)
                ) {
                    Text("Request reschedule", color = Color.White)
                }
            }
        }
    }
}
